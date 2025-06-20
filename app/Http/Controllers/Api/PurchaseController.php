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
        // if ($request->filled('supplier')) {
        //     $query->where('suppliers.name', 'like', '%' . $request->supplier . '%');
        // }

        // Filter pencarian jika parameter q tidak kosong
        if ($request->filled('q') && !empty($request->q)) {
            $search = $request->q;
            $query->where(function($q) use ($search) {
                $q->where('purchases.unique_code', 'like', "%{$search}%")
                  ->orWhereExists(function ($query) use ($search) {
                      $query->select(DB::raw(1))
                            ->from('suppliers')
                            ->whereColumn('suppliers.id', 'purchases.supplier_id')
                            ->where('suppliers.name', 'like', "%{$search}%");
                  });
            });
        }


        if ($request->filled('status')) {
            if ($request->status === 'order') {
                $query->whereNotNull('purchases.purchase_order_id');
            } elseif ($request->status === 'langsung') {
                $query->whereNull('purchases.purchase_order_id');
            } elseif ($request->status === 'semua') {
                // Tidak ada filter tambahan
            } else {
                $query->where('purchase_orders.status', $request->status);
            }
        }

        // Filter berdasarkan rentang tanggal jika ada
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }


        // $purchases = $query->orderByDesc('purchases.id')->simplePaginate(15);
        $perPage = $request->input('per_page', 10);
        $totalCount = (clone $query)->count();

        // Lakukan pagination dengan simplePaginate
        $purchases = $query->simplePaginate($perPage);

        $data = [
            'data' => $purchases->items(),
            'meta' => [
                'first' => $purchases->url(1),
                'last' => null, // SimplePaginator tidak menyediakan ini
                'prev' => $purchases->previousPageUrl(),
                'next' => $purchases->nextPageUrl(),
                'current_page' => $purchases->currentPage(),
                'per_page' => (int)$perPage,
                'total' => (int)$totalCount,
                'last_page' => ceil($totalCount / $perPage),
                'from' => (($purchases->currentPage() - 1) * $perPage) + 1,
                'to' => min($purchases->currentPage() * $perPage, $totalCount),
            ],
        ];

        return response()->json($data);
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
                'payment_method' => 'required|string|in:cash,transfer,credit',
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

            // Gunakan DB::transaction dengan closure untuk rollback otomatis jika terjadi error
            return DB::transaction(function () use ($validated, $dueDate) {
                try {
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
                                throw new \Exception('Item PO tidak valid untuk diproses pembelian.');
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
                        'payment_method' => $validated['payment_method'],
                        'invoice_number' => $validated['invoice_number'] ?? null,
                        'skip_stock_mutation' => true, // Flag untuk skip mutasi stok di observer
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
                        // $product = Product::find($item['product_id']);
                        // if (!$product) {
                        //     throw new \Exception("Produk dengan ID {$item['product_id']} tidak ditemukan.");
                        // }

                        // $product->increment('stock', $item['qty']);

                        // // Catat mutasi stok produk dengan metode createMutation
                        // $lastMutation = ProductStockMutation::getLastMutation($item['product_id']);
                        // $stockBefore = $lastMutation ? $lastMutation->stock_after : 0;

                        // $notes = !empty($po)
                        //     ? 'Pembelian dari PO #' . $po->unique_code
                        //     : 'Pembelian langsung tanpa PO';

                        // ProductStockMutation::createMutation([
                        //     'product_id' => $item['product_id'],
                        //     'mutation_type' => 'in',
                        //     'qty' => $item['qty'],
                        //     'stock_before' => $stockBefore,
                        //     'stock_after' => $stockBefore + $item['qty'],
                        //     'source_type' => 'purchase',
                        //     'source_id' => $purchase->id,
                        //     'notes' => $notes,
                        // ]);

                        // Update status item PO jika ada
                        if (!empty($item['purchase_order_item_id'])) {
                            $poItem = PurchaseOrderItem::find($item['purchase_order_item_id']);
                            if (!$poItem) {
                                throw new \Exception("Item PO dengan ID {$item['purchase_order_item_id']} tidak ditemukan.");
                            }
                            $poItem->status = 'active';
                            $poItem->save();
                        }
                    }

                    // PERBAIKAN: Gunakan createHistory untuk hutang supplier
                    // Dapatkan supplier dan hutangnya
                    $supplier = Supplier::find($supplier_id);
                    if (!$supplier) {
                        throw new \Exception("Supplier dengan ID {$supplier_id} tidak ditemukan.");
                    }

                    // $supplierDebt = $supplier->debt;

                    // // Jika belum ada catatan hutang, buat baru
                    // if (!$supplierDebt && ($total - $validated['paid'] > 0)) {
                    //     $supplierDebt = $supplier->debt()->create([
                    //         'initial_amount' => 0,
                    //         'current_amount' => 0, // Akan diupdate oleh createHistory
                    //         'notes' => 'Hutang dari pembelian #' . $purchase->unique_code
                    //     ]);
                    // }

                    // Catat histori hutang supplier jika ada hutang
                    // if ($supplierDebt && ($total - $validated['paid'] > 0)) {
                    //     $notes = !empty($po)
                    //         ? 'Pembelian dari PO #' . $po->unique_code
                    //         : 'Pembelian langsung tanpa PO';

                    //     // Gunakan createHistory untuk konsistensi dengan running balance
                    //     SupplierDebtHistory::createHistory([
                    //         'supplier_debt_id' => $supplierDebt->id,
                    //         'mutation_type' => 'increase',
                    //         'amount' => $total - $validated['paid'],
                    //         'source_type' => 'purchase',
                    //         'source_id' => $purchase->id,
                    //         'notes' => $notes,
                    //     ]);
                    // }

                    // Setelah semua item pembelian diproses
                    if (!empty($po)) {
                        $po->updateStatus();
                    }

                    return response()->json($purchase->load(['supplier', 'purchaseOrder', 'items.product']), 201);
                } catch (\Exception $e) {
                    // Tangkap error dan throw kembali untuk memicu rollback
                    Log::error('Error dalam transaksi pembelian: ' . $e->getMessage(), [
                        'exception' => $e,
                        'trace' => $e->getTraceAsString()
                    ]);

                    // Throw exception untuk memicu rollback otomatis
                    throw $e;
                }
            }, 5); // Retry 5 kali jika terjadi deadlock
        } catch (\Throwable $e) {
            Log::error('Gagal menyimpan transaksi pembelian: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);

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
        try {
            $purchase = Purchase::with(['items'])->findOrFail($id);

            return DB::transaction(function () use ($purchase) {
                try {
                    // Rollback stok produk dan catat mutasi keluar
                    foreach ($purchase->items as $item) {
                        $product = Product::find($item->product_id);
                        if (!$product) {
                            throw new \Exception("Produk dengan ID {$item->product_id} tidak ditemukan.");
                        }

                        // Validasi stok cukup untuk dikurangi
                        if ($product->stock < $item->qty) {
                            throw new \Exception("Stok produk {$product->name} tidak cukup untuk dibatalkan.");
                        }

                        $product->decrement('stock', $item->qty);

                        // Ambil stok terakhir untuk dikirim ke job
                        $lastMutation = ProductStockMutation::getLastMutation($item->product_id);
                        if (!$lastMutation) {
                            throw new \Exception("Tidak ada catatan mutasi stok untuk produk ID {$item->product_id}.");
                        }

                        $stockBefore = $lastMutation->stock_after;
                        $stockAfter = $stockBefore - $item->qty;

                        // Validasi stok tidak negatif
                        if ($stockAfter < 0) {
                            throw new \Exception("Pembatalan akan menyebabkan stok negatif untuk produk ID {$item->product_id}.");
                        }

                        ProductStockMutation::createMutation([
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

                    // Rollback hutang supplier dan catat histori
                    $supplier = Supplier::find($purchase->supplier_id);
                    if (!$supplier) {
                        throw new \Exception("Supplier dengan ID {$purchase->supplier_id} tidak ditemukan.");
                    }

                    if ($supplier && $purchase->debt > 0) {
                        $supplierDebt = $supplier->debt;
                        if (!$supplierDebt) {
                            throw new \Exception("Tidak ada catatan hutang untuk supplier ID {$purchase->supplier_id}.");
                        }

                        // Validasi saldo hutang cukup untuk dikurangi
                        if ($supplierDebt->current_amount < $purchase->debt) {
                            throw new \Exception("Saldo hutang supplier tidak cukup untuk dibatalkan.");
                        }

                        // PERBAIKAN: Gunakan createHistory untuk konsistensi
                        SupplierDebtHistory::createHistory([
                            'supplier_debt_id' => $supplierDebt->id,
                            'mutation_type' => 'decrease',
                            'amount' => $purchase->debt,
                            'source_type' => 'purchase_cancel',
                            'source_id' => $purchase->id,
                            'notes' => 'Pembatalan pembelian',
                        ]);
                    }

                    // Hapus purchase setelah semua rollback berhasil
                    $purchase->delete();

                    return response()->json(['message' => 'Transaksi pembelian berhasil dihapus']);
                } catch (\Exception $e) {
                    // Tangkap error dan throw kembali untuk memicu rollback
                    Log::error('Error dalam pembatalan pembelian: ' . $e->getMessage(), [
                        'exception' => $e,
                        'trace' => $e->getTraceAsString()
                    ]);

                    // Throw exception untuk memicu rollback otomatis
                    throw $e;
                }
            }, 5); // Retry 5 kali jika terjadi deadlock
        } catch (\Throwable $e) {
            Log::error('Gagal membatalkan transaksi pembelian: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Terjadi kesalahan saat membatalkan transaksi pembelian.',
                'error' => app()->environment('production') ? null : $e->getMessage()
            ], 500);
        }
    }
}
