<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PenerimaanGudang;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierDebtHistory;
use App\Models\ProductStockMutation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseController extends Controller
{
    public function index(Request $request)
    {
        $query = Purchase::query()
            ->select('purchases.*', 'suppliers.name as supplier_name', 'purchase_orders.status as status_order', 'users.name as user_name')
            ->leftJoin('suppliers', 'purchases.supplier_id', '=', 'suppliers.id')
            ->leftJoin('users', 'purchases.user_id', '=', 'users.id')
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
                ->orWhere('purchases.invoice_number', 'like', "%{$search}%")
                ->orWhere('suppliers.name', 'like', "%{$search}%")
                ->orWhere('users.name', 'like', "%{$search}%");
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
        $purchases = $query->orderBy($request->sort_by ?? 'purchases.created_at', $request->sort_direction ? 'desc' : 'asc')->simplePaginate($perPage);

        $data = [
            'data' => $purchases->items(),
            'meta' => [
                'first' => $purchases->url(1),
                'last' => $purchases->url(ceil($totalCount / $perPage)),
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


    public function search(Request $request)
    {
        $search = $request->input('q');
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);
        $offset = ($page - 1) * $perPage;

        // Base query reuseable
        $baseQuery = function ($q) use ($search) {
            $q->leftJoin('suppliers', 'purchases.supplier_id', '=', 'suppliers.id')
            ->leftJoin('purchase_items', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->leftJoin('products', 'purchase_items.product_id', '=', 'products.id')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('purchases.unique_code', 'like', "%{$search}%")
                        ->orWhere('suppliers.name', 'like', "%{$search}%")
                        ->orWhere('products.name', 'like', "%{$search}%");
                });
            });
        };

        // Count total unique sales.id
        $totalCount = DB::table('purchases')
            ->when(true, $baseQuery)
            ->distinct('purchases.id')
            ->count('purchases.id');

        // Get paginated data
        $query = Purchase::query()
            ->select(
                'purchases.*',
                'suppliers.name as supplier_name'
            )
            ->when(true, $baseQuery)
            ->with(['supplier', 'items.product'])
            ->groupBy('purchases.id', 'purchases.unique_code', 'purchases.created_at', 'suppliers.name')
            ->orderByDesc('purchases.created_at')
            ->offset($offset)
            ->limit($perPage);

        $sales = $query->get();

        return response()->json([
            'data' => $sales,
            'meta' => [
                'current_page' => (int) $page,
                'per_page' => (int) $perPage,
                'total' => (int) $totalCount,
                'last_page' => ceil($totalCount / $perPage),
                'from' => $offset + 1,
                'to' => $offset + $sales->count(),
                'prev' => $page > 1 ? $page - 1 : null,
                'next' => ($offset + $sales->count()) < $totalCount ? $page + 1 : null,
            ]
        ]);
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
                'items.*.qty_gudang' => 'required|integer|min:0',
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
                            'qty' => $item['qty'] +( $item['qty_gudang'] ?? 0),
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

                    $userId = Auth::id() ?? null;
                    $purchaseData = [
                        'supplier_id' => $supplier_id,
                        'user_id' => $userId,
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

                    

                    // Setelah semua item pembelian diproses
                    if (!empty($po)) {
                        $po->updateStatus();
                    }


                    // ini jika ada qty gudang di rincian
                    $hasQtyGudang = collect($validated['items'])->pluck('qty_gudang')->filter(fn($q) => $q > 0)->isNotEmpty();
                    if ($hasQtyGudang) {
                        $penerimaanCode = 'PG-' . now()->format('Ymd') . '-' . $purchase->id;
                        $penerimaan = PenerimaanGudang::create([
                            'purchase_id' => $purchase->id,
                            'user_id' => $userId,
                            'received_at' => Carbon::now(),
                            'notes' => $validated['note'] ?? null,
                            'reference_no' => $penerimaanCode,
                        ]);

                        foreach ($validated['items'] as $item) {
                            if ($item['qty_gudang'] > 0) {
                                $penerimaan->items()->create([
                                    'product_id' => $item['product_id'],
                                    'qty' => $item['qty_gudang'],
                                ]);
                            }
                        }
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


    public function report(Request $request)
    {
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 20);
        $offset = ($page - 1) * $perPage;

        $query = Purchase::query()
            ->select(
                'purchases.*',
                'suppliers.name as supplier_name',
                'users.name as user_name'
            )
            ->leftJoin('suppliers', 'purchases.supplier_id', '=', 'suppliers.id')
            ->leftJoin('users', 'purchases.user_id', '=', 'users.id')
            ->when($request->filled('q'), function ($q) use ($request) {
                $q->where(function ($q) use ($request) {
                    $q->where('suppliers.name', 'like', "%{$request->q}%")
                    ->orWhere('users.name', 'like', "%{$request->q}%")
                    ->orWhere('purchases.unique_code', 'like', "%{$request->q}%")
                    ->orWhere('purchases.invoice_number', 'like', "%{$request->q}%");
                });
            })
            ->when($request->filled('start_date') && $request->filled('end_date'), function ($q) use ($request) {
                $start = $request->start_date . ' 00:00:00';
                $end = $request->end_date . ' 23:59:59';
                $q->whereBetween('purchases.created_at', [$start, $end]);
            })
            ->with(['supplier','items.product:id,name,barcode'])
            // ->orderByDesc('purchases.date');
            ->orderBy($request->sort_by ?? 'purchases.created_at', $request->sort_direction ? 'desc' : 'asc');

        $totalCount = (clone $query)->count();

        $data = $query
            ->offset($offset)
            ->limit($perPage)
            ->get()
            ->map(function ($item) {
                $item->status_pembayaran = $item->debt <= 0
                    ? 'lunas'
                    : ($item->paid > 0 ? 'sebagian' : 'belum lunas');
                return $item;
            });

        // $data = $query->simplePaginate($perPage);

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $totalCount,
                'last_page' => ceil($totalCount / $perPage),
                'from' => $offset + 1,
                'to' => $offset + $data->count(),
                'prev' => $page > 1 ? $page - 1 : null,
                'next' => ($offset + $data->count()) < $totalCount ? $page + 1 : null,
            ]
        ]);
    }

    public function rekap(Request $request)
    {
       $query = Purchase::query()
        ->join('suppliers', 'purchases.supplier_id', '=', 'suppliers.id')
        ->when($request->filled('q'), function ($q) use ($request) {
            $q->where(function ($q) use ($request) {
                $q->where('suppliers.name', 'like', "%{$request->q}%")
                  ->orWhere('purchases.unique_code', 'like', "%{$request->q}%")
                  ->orWhere('purchases.invoice_number', 'like', "%{$request->q}%");
            });
        })
       ->when($request->filled('start_date') && $request->filled('end_date'), function ($q) use ($request) {
            $start = $request->start_date . ' 00:00:00';
            $end = $request->end_date . ' 23:59:59';
            $q->whereBetween('purchases.created_at', [$start, $end]);
        });

        $totalBelanja = (clone $query)->sum('purchases.total');
        $totalLunas   = (clone $query)->where('purchases.debt', '<=', 0)->sum('purchases.total');
        $totalSebagian= (clone $query)->where('purchases.debt', '>', 0)->where('purchases.paid', '>', 0)->sum('purchases.total');
        $totalBelum   = (clone $query)->where('purchases.debt', '>', 0)->where('purchases.paid', '=', 0)->sum('purchases.total');

        return response()->json([
            'total_belanja' => $totalBelanja,
            'total_lunas' => $totalLunas,
            'total_sebagian' => $totalSebagian,
            'total_belum_lunas' => $totalBelum,
        ]);
    }
}
