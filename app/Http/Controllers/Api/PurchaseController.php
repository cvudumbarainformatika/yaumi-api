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
                'purchase_order_id' => 'required|exists:purchase_orders,id',
                'purchase_date' => 'required|date',
                'items' => 'required|array|min:1',
                'items.*.purchase_order_item_id' => 'required|exists:purchase_order_items,id',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.qty' => 'required|integer|min:1',
                'items.*.price' => 'required|numeric|min:0',
                'note' => 'nullable|string',
            ]);

            return DB::transaction(function () use ($validated) {
                $po = PurchaseOrder::findOrFail($validated['purchase_order_id']);
                $supplier_id = $po->supplier_id;
                $total = 0;
                $itemsData = [];
                foreach ($validated['items'] as $item) {
                    $poItem = PurchaseOrderItem::findOrFail($item['purchase_order_item_id']);
                    if (!in_array($poItem->status, ['ordered', 'active'])) {
                        abort(422, 'Item PO tidak valid untuk diproses pembelian.');
                    }
                    $subtotal = $item['qty'] * $item['price'];
                    $total += $subtotal;
                    $itemsData[] = [
                        'purchase_order_item_id' => $item['purchase_order_item_id'],
                        'product_id' => $item['product_id'],
                        'qty' => $item['qty'],
                        'price' => $item['price'],
                        'subtotal' => $subtotal,
                    ];
                }
                $uniqueCode = 'PB-' . date('Ymd') . '-' . uniqid();
                $purchase = Purchase::create([
                    'purchase_order_id' => $validated['purchase_order_id'],
                    'supplier_id' => $validated['supplier_id'],
                    'purchase_date' => $validated['purchase_date'],
                    'total' => $total,
                    'paid' => $validated['paid'],
                    'debt' => $total - $validated['paid'],
                    'note' => $validated['note'] ?? null,
                    'unique_code' => $uniqueCode,
                ]);
                foreach ($itemsData as $item) {
                    $purchaseItem = $purchase->items()->create($item);
                    // Update stok produk
                    $product = Product::find($item['product_id']);
                    $product->increment('stok', $item['qty']);
                    // Catat mutasi stok produk
                    ProductStockMutation::create([
                        'product_id' => $item['product_id'],
                        'mutation_type' => 'in',
                        'qty' => $item['qty'],
                        'source_type' => 'purchase',
                        'source_id' => $purchase->id,
                        'notes' => 'Pembelian dari PO #' . $po->order_number,
                    ]);
                    // Update status item PO
                    $poItem = PurchaseOrderItem::find($item['purchase_order_item_id']);
                    $poItem->status = 'active';
                    $poItem->save();
                }
                // Update hutang supplier
                $supplier = Supplier::find($supplier_id);
                $supplier->increment('saldo_hutang', $total);
                // Catat histori hutang supplier
                $supplierDebt = $supplier->debt;
                if ($supplierDebt) {
                    SupplierDebtHistory::create([
                        'supplier_debt_id' => $supplierDebt->id,
                        'mutation_type' => 'increase',
                        'amount' => $total,
                        'source_type' => 'purchase',
                        'source_id' => $purchase->id,
                        'notes' => 'Pembelian dari PO #' . $po->order_number,
                    ]);
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
                $product->decrement('stok', $item->qty);
                ProductStockMutation::create([
                    'product_id' => $item->product_id,
                    'mutation_type' => 'out',
                    'qty' => $item->qty,
                    'source_type' => 'purchase_cancel',
                    'source_id' => $purchase->id,
                    'notes' => 'Pembatalan pembelian',
                ]);
            }
        }
        // Rollback hutang supplier dan catat histori
        $supplier = Supplier::find($purchase->supplier_id);
        if ($supplier) {
            $supplier->decrement('saldo_hutang', $purchase->total);
            $supplierDebt = $supplier->debt;
            if ($supplierDebt) {
                SupplierDebtHistory::create([
                    'supplier_debt_id' => $supplierDebt->id,
                    'mutation_type' => 'decrease',
                    'amount' => $purchase->total,
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
