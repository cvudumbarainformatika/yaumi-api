<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierDebtHistory;
use App\Models\ProductStockMutation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseController extends Controller
{
    public function index(Request $request)
    {
        $query = Purchase::query()
            ->select('purchases.*', 'suppliers.name as supplier_name', 'purchase_orders.status as status_order')
            ->leftJoin('suppliers', 'purchases.supplier_id', '=', 'suppliers.id')
            ->leftJoin('purchase_orders', 'purchases.purchase_order_id', '=', 'purchase_orders.id')
            ->with(['supplier', 'purchaseOrder', 'items.product']);
        if ($request->filled('supplier')) {
            $query->where('suppliers.name', 'like', '%' . $request->supplier . '%');
        }
        if ($request->filled('status')) {
            $query->where('purchase_orders.status', $request->status);
        }
        $purchases = $query->orderByDesc('purchases.id')->simplePaginate(15);
        return response()->json($purchases);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'purchase_order_id' => 'nullable|exists:purchase_orders,id',
                'supplier_id' => 'required|exists:suppliers,id',
                'date' => 'required|date',
                'due_date' => 'nullable|date|after_or_equal:date',
                'paid' => 'required|numeric|min:0',
                'payment_method' => 'required|string|in:cash,transfer,credit', // Tambahkan validasi metode pembayaran
                'invoice_number' => 'nullable|string|max:255',
                'items' => 'required|array|min:1',
                'items.*.purchase_order_item_id' => 'nullable|exists:purchase_order_items,id',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.qty' => 'required|integer|min:1',
                'items.*.price' => 'required|numeric|min:0',
                'note' => 'nullable|string',
            ]);

            // Set due_date berdasarkan metode pembayaran
            if ($validated['payment_method'] === 'credit') {
                // Untuk pembelian kredit
                if (!isset($validated['due_date'])) {
                    // Default 30 hari untuk kredit jika due_date tidak disediakan
                    $purchaseDate = new \DateTime($validated['date']);
                    $dueDate = $purchaseDate->modify('+30 days')->format('Y-m-d');
                } else {
                    $dueDate = $validated['due_date'];
                }
            } else {
                // Untuk pembelian tunai atau transfer, due_date adalah tanggal pembelian
                $dueDate = $validated['date'];
            }

            return DB::transaction(function () use ($validated, $dueDate) {
                $supplier_id = null;
                $po = null;
                
                // Jika ada purchase_order_id, ambil data PO
                if (!empty($validated['purchase_order_id'])) {
                    $po = PurchaseOrder::findOrFail($validated['purchase_order_id']);
                    $supplier_id = $po->supplier_id;
                } else {
                    // Jika tidak ada PO, gunakan supplier_id dari request
                    $supplier_id = $validated['supplier_id'];
                }
                
                $total = 0;
                $itemsData = [];
                
                foreach ($validated['items'] as $item) {
                    // Jika ada purchase_order_item_id, validasi status item PO
                    if (!empty($item['purchase_order_item_id'])) {
                        $poItem = PurchaseOrderItem::findOrFail($item['purchase_order_item_id']);
                        if (!in_array($poItem->status, ['ordered', 'active'])) {
                            abort(422, 'Item PO tidak valid untuk diproses pembelian.');
                        }
                    }
                    
                    $subtotal = $item['qty'] * $item['price'];
                    $total += $subtotal;
                    
                    $itemData = [
                        'product_id' => $item['product_id'],
                        'qty' => $item['qty'],
                        'price' => $item['price'],
                        'subtotal' => $subtotal,
                    ];
                    
                    // Tambahkan purchase_order_item_id jika ada
                    if (!empty($item['purchase_order_item_id'])) {
                        $itemData['purchase_order_item_id'] = $item['purchase_order_item_id'];
                    }
                    
                    $itemsData[] = $itemData;
                }
                
                $uniqueCode = 'PB-' . date('Ymd') . '-' . substr(uniqid(), -4);
                
                // Buat data purchase
                $purchaseData = [
                    'supplier_id' => $supplier_id,
                    'date' => $validated['date'],
                    'due_date' => $dueDate,
                    'total' => $total,
                    'paid' => $validated['paid'],
                    'debt' => $total - $validated['paid'],
                    'note' => $validated['note'] ?? null,
                    'unique_code' => $uniqueCode,
                    'payment_method' => $validated['payment_method'], // Tambahkan payment_method
                    'invoice_number' => $validated['invoice_number'] ?? null,
                    'skip_stock_mutation' => true, // Tambahkan flag untuk skip mutasi stok di observer
                ];
                
                // Tambahkan purchase_order_id jika ada
                if (!empty($validated['purchase_order_id'])) {
                    $purchaseData['purchase_order_id'] = $validated['purchase_order_id'];
                }
                
                $purchase = Purchase::create($purchaseData);
                
                // Proses item-item pembelian
                foreach ($itemsData as $item) {
                    $purchaseItem = $purchase->items()->create($item);
                    
                    // Update stok produk
                    $product = Product::find($item['product_id']);
                    $product->increment('stock', $item['qty']); // Ubah dari 'stok' menjadi 'stock'
                    
                    // Catat mutasi stok produk dengan metode createMutation
                    $lastMutation = ProductStockMutation::getLastMutation($item['product_id']);
                    $stockBefore = $lastMutation ? $lastMutation->stock_after : 0;

                    $notes = !empty($po) 
                        ? 'Pembelian dari PO #' . $po->unique_code 
                        : 'Pembelian langsung tanpa PO';
                        
                    ProductStockMutation::createMutation([
                        'product_id' => $item['product_id'],
                        'mutation_type' => 'in',
                        'qty' => $item['qty'],
                        'stock_before' => $stockBefore,
                        'stock_after' => $stockBefore + $item['qty'],
                        'source_type' => 'purchase',
                        'source_id' => $purchase->id,
                        'notes' => $notes,
                    ]);
                    
                    // Update status item PO jika ada
                    if (!empty($item['purchase_order_item_id'])) {
                        $poItem = PurchaseOrderItem::find($item['purchase_order_item_id']);
                        $poItem->status = 'active';
                        $poItem->save();
                    }
                }
                
                // Update hutang supplier
                $supplier = Supplier::find($supplier_id);
                $supplierDebt = $supplier->debt;
                if ($supplierDebt) {
                    $supplierDebt->increment('current_amount', $total - $validated['paid']);
                } else {
                    // Jika belum ada catatan hutang, buat baru
                    $supplier->debt()->create([
                        'initial_amount' => 0,
                        'current_amount' => $total - $validated['paid'],
                        'notes' => 'Hutang dari pembelian #' . $purchase->unique_code
                    ]);
                }

                // Catat histori hutang supplier
                $supplierDebt = $supplier->debt;
                if ($supplierDebt && ($total - $validated['paid'] > 0)) {
                    $notes = !empty($po) 
                        ? 'Pembelian dari PO #' . $po->unique_code 
                        : 'Pembelian langsung tanpa PO';
                        
                    SupplierDebtHistory::create([
                        'supplier_debt_id' => $supplierDebt->id,
                        'mutation_type' => 'increase',
                        'amount' => $total - $validated['paid'],
                        'source_type' => 'purchase',
                        'source_id' => $purchase->id,
                        'notes' => $notes,
                    ]);
                }

                // Setelah semua item pembelian diproses
                if (!empty($po)) {
                    $po->updateStatus();
                }
                
                return response()->json($purchase->load(['supplier', 'purchaseOrder', 'items.product']), 201);
            });
        } catch (\Throwable $e) {
            Log::error('Gagal menyimpan transaksi pembelian: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'message' => 'Terjadi kesalahan saat memproses transaksi pembelian.',
                'error' => app()->environment('production') ? null : $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $purchase = Purchase::with(['supplier', 'purchaseOrder', 'items.product'])->findOrFail($id);
        return response()->json($purchase);
    }

    public function destroy($id)
    {
        $purchase = Purchase::with(['items'])->findOrFail($id);
        // Rollback stok produk dan catat mutasi keluar
        foreach ($purchase->items as $item) {
            $product = Product::find($item->product_id);
            if ($product) {
                $product->decrement('stock', $item->qty);
                
                // Ambil stok terakhir untuk dikirim ke job
                $lastMutation = ProductStockMutation::getLastMutation($item->product_id);
                $stockBefore = $lastMutation ? $lastMutation->stock_after : 0;
                $stockAfter = $stockBefore - $item->qty;
                
                ProductStockMutation::create([
                    'product_id' => $item->product_id,
                    'mutation_type' => 'out',
                    'qty' => $item->qty,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'source_type' => 'purchase_cancel',
                    'source_id' => $purchase->id,
                    'notes' => 'Pembatalan pembelian',
                ]);
            }
        }
        
        // Rollback hutang supplier dan catat histori
        $supplier = Supplier::find($purchase->supplier_id);
        if ($supplier && $purchase->debt > 0) {
            $supplierDebt = $supplier->debt;
            if ($supplierDebt) {
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
        
        $purchase->delete();
        return response()->json(['message' => 'Transaksi pembelian dihapus']);
    }
}
