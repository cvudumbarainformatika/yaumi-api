<?php

namespace App\Observers;

use App\Models\Purchase;
use App\Models\ProductStockMutation;
use App\Models\SupplierDebtHistory;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use App\Jobs\ProcessStockMutation;

class PurchaseObserver
{
    /**
     * Handle events after all transactions are committed.
     *
     * @var bool
     */
    public $afterCommit = true;

    /**
     * Handle the Purchase "created" event.
     */
    public function created(Purchase $purchase): void
    {
        // Proses mutasi stok secara asinkron untuk menghindari blocking
        foreach ($purchase->items as $item) {
            // Ambil stok terakhir untuk dikirim ke job
            $lastMutation = ProductStockMutation::getLastMutation($item->product_id);
            $stockBefore = $lastMutation ? $lastMutation->stock_after : 0;
            
            ProcessStockMutation::dispatch([
                'product_id' => $item->product_id,
                'mutation_type' => 'in',
                'qty' => $item->qty,
                'stock_before' => $stockBefore,
                'stock_after' => $stockBefore + $item->qty,
                'source_type' => 'purchase',
                'source_id' => $purchase->id,
                'notes' => $purchase->purchase_order_id 
                    ? 'Pembelian dari PO #' . $purchase->purchaseOrder->unique_code 
                    : 'Pembelian langsung tanpa PO',
            ]);
        }
        
        // Proses hutang supplier
        $supplier = Supplier::find($purchase->supplier_id);
        $supplierDebt = $supplier->debt;
        if ($supplierDebt && $purchase->debt > 0) {
            // Catat histori hutang dengan running balance
            SupplierDebtHistory::createHistory([
                'supplier_debt_id' => $supplierDebt->id,
                'mutation_type' => 'increase',
                'amount' => $purchase->debt,
                'source_type' => 'purchase',
                'source_id' => $purchase->id,
                'notes' => $purchase->purchase_order_id 
                    ? 'Pembelian dari PO #' . $purchase->purchaseOrder->unique_code 
                    : 'Pembelian langsung tanpa PO',
            ]);
        }
    }

    /**
     * Handle the Purchase "deleted" event.
     */
    public function deleted(Purchase $purchase): void
    {
        DB::transaction(function () use ($purchase) {
            // Rollback stok produk
            foreach ($purchase->items as $item) {
                // Catat mutasi stok keluar dengan running balance
                ProductStockMutation::createMutation([
                    'product_id' => $item->product_id,
                    'mutation_type' => 'out',
                    'qty' => $item->qty,
                    'source_type' => 'purchase_cancel',
                    'source_id' => $purchase->id,
                    'notes' => 'Pembatalan pembelian',
                ]);
                
                // Update stok produk
                $product = Product::find($item->product_id);
                if ($product) {
                    $product->update([
                        'stock' => ProductStockMutation::getLastMutation($item->product_id)->stock_after // Ubah dari 'stok' menjadi 'stock'
                    ]);
                }
            }
            
            // Rollback hutang supplier
            $supplier = Supplier::find($purchase->supplier_id);
            if ($supplier && $purchase->debt > 0) {
                $supplierDebt = $supplier->debt;
                if ($supplierDebt) {
                    // Catat histori hutang dengan running balance
                    SupplierDebtHistory::createHistory([
                        'supplier_debt_id' => $supplierDebt->id,
                        'mutation_type' => 'decrease',
                        'amount' => $purchase->debt,
                        'source_type' => 'purchase_cancel',
                        'source_id' => $purchase->id,
                        'notes' => 'Pembatalan pembelian',
                    ]);
                }
            }
        });
    }
}
