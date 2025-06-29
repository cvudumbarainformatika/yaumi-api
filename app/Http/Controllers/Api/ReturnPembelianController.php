<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReturnPembelian;
use App\Models\ReturnPenjualan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReturnPembelianController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // return ReturnPenjualan::with('customer', 'items.product', 'penjualan')->latest()->simplePaginate(20);
        $query = ReturnPembelian::query()
        ->select('return_pembelians.*', 'suppliers.name as supplier_name', 'users.name as user_name')
        ->leftJoin('suppliers', 'return_pembelians.supplier_id', '=', 'suppliers.id')
        ->leftJoin('users', 'return_pembelians.user_id', '=', 'users.id')
        ->with(['supplier', 'items.product', 'pembelian']);

        // 🔍 Filter pencarian global
        if ($request->filled('q') && !empty($request->q)) {
            $search = $request->q;

            $query->where(function($q) use ($search) {
                $q->where('return_pembelians.unique_code', 'like', "%{$search}%")
                ->orWhere('suppliers.name', 'like', "%{$search}%");
            });
        }

        // 🧾 Filter berdasarkan metode pembayaran (jika ada)
        // if ($request->filled('status') && $request->status !== 'semua') {
        //     $query->where('return_pembelians.payment_method', $request->status); // jika kamu menyimpan metode pembayaran di sini
        // }

        // 📅 Filter tanggal
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('return_pembelians.tanggal', [
                $request->start_date, $request->end_date
            ]);
        }

        // 📄 Pagination
        $perPage = $request->input('per_page', 10);
        $totalCount = (clone $query)->count();
        $returns = $query->latest('return_pembelians.created_at')->simplePaginate($perPage);

        $data = [
            'data' => $returns->items(),
            'meta' => [
                'first' => $returns->url(1),
                'last' => null,
                'prev' => $returns->previousPageUrl(),
                'next' => $returns->nextPageUrl(),
                'current_page' => $returns->currentPage(),
                'per_page' => (int)$perPage,
                'total' => (int)$totalCount,
                'last_page' => ceil($totalCount / $perPage),
                'from' => (($returns->currentPage() - 1) * $perPage) + 1,
                'to' => min($returns->currentPage() * $perPage, $totalCount),
            ],
        ];

        return response()->json($data);
    }
   

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => 'nullable|exists:suppliers,id',
            'purchase_id' => 'nullable|exists:purchases,id',
            'keterangan' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty' => 'required|numeric|min:0',
            'items.*.harga' => 'required|numeric|min:0',
            'items.*.alasan' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $kode = 'RTP-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4));
            $total = collect($data['items'])->sum(fn($i) => $i['qty'] * $i['harga']);

            $retur = ReturnPembelian::create([
                'unique_code' => $kode,
                'supplier_id' => $data['supplier_id'],
                'purchase_id' => $data['purchase_id'],
                'user_id' => Auth::id(),
                'tanggal' => date('Y-m-d H:i:s'),
                'keterangan' => $data['keterangan'] ?? null,
                'total' => $total
            ]);

            foreach ($data['items'] as $item) {
                $retur->items()->create([
                    'product_id' => $item['product_id'],
                    'qty' => $item['qty'],
                    'harga' => $item['harga'],
                    'subtotal' => $item['qty'] * $item['harga'],
                    'alasan' => $item['alasan']
                ]);
            }

            DB::commit();
            return response()->json(['message' => 'Retur pembelian berhasil disimpan', 'data' => $retur->load('items.product')]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal menyimpan retur pembelian', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(ReturnPenjualan $returnPenjualan)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ReturnPenjualan $returnPenjualan)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ReturnPenjualan $returnPenjualan)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ReturnPenjualan $returnPenjualan)
    {
        //
    }

    public function report(Request $request)
    {
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 15);
        $offset = ($page - 1) * $perPage;

        $query = ReturnPembelian::query()
            ->join('suppliers', 'return_pembelians.supplier_id', '=', 'suppliers.id')
            ->leftJoin('return_pembelian_items', 'return_pembelians.id', '=', 'return_pembelian_items.return_pembelian_id')
            ->select(
                'return_pembelians.*',
                'suppliers.name as supplier_name',
                DB::raw('SUM(return_pembelian_items.subtotal) as total_items'),
                DB::raw('COUNT(return_pembelian_items.id) as jumlah_item')
            )
            ->when($request->filled('q'), fn($q) =>
                $q->where(function ($q) use ($request) {
                    $q->where('return_pembelians.unique_code', 'like', '%' . $request->q . '%')
                    ->orWhere('suppliers.name', 'like', '%' . $request->q . '%');
                })
            )
            ->when($request->filled('start_date') && $request->filled('end_date'), fn($q) =>
                $q->whereBetween('return_pembelians.tanggal', [$request->start_date, $request->end_date])
            )
            ->groupBy(
                'return_pembelians.id',
                'return_pembelians.unique_code',
                'return_pembelians.tanggal',
                'return_pembelians.total',
                'return_pembelians.supplier_id',
                'suppliers.name',
                'return_pembelians.created_at',
                'return_pembelians.updated_at'
            )
            ->orderBy('return_pembelians.tanggal', 'desc');

        $totalCount = (clone $query)->count();

        $items = $query
            ->with(['supplier', 'items.product'])
            ->offset($offset)
            ->limit($perPage)
            ->get();

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $totalCount,
                'last_page' => ceil($totalCount / $perPage),
                'from' => $offset + 1,
                'to' => $offset + $items->count(),
                'prev' => $page > 1 ? $page - 1 : null,
                'next' => ($offset + $items->count()) < $totalCount ? $page + 1 : null,
            ]
        ]);
    }

    public function rekap(Request $request)
    {
       $baseQuery = ReturnPembelian::query()
        ->leftJoin('return_pembelian_items', 'return_pembelians.id', '=', 'return_pembelian_items.return_pembelian_id');

        // Filter pencarian
        if ($request->filled('q')) {
            $q = $request->q;
            $baseQuery->leftJoin('suppliers', 'return_pembelians.supplier_id', '=', 'suppliers.id')
                ->where(function ($query) use ($q) {
                    $query->where('suppliers.name', 'like', "%{$q}%")
                        ->orWhere('return_pembelians.unique_code', 'like', "%{$q}%");
                });
        }

        // Filter tanggal
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $baseQuery->whereBetween('return_pembelians.tanggal', [$request->start_date, $request->end_date]);
        }

        // Summary utama
        $summary = (clone $baseQuery)->selectRaw('
            COUNT(DISTINCT return_pembelians.id) as jumlah_transaksi,
            SUM(return_pembelians.total) as total_retur,
            SUM(return_pembelian_items.qty) as total_barang
        ')->first();

        // Alasan retur terbanyak
        $topAlasan = (clone $baseQuery)
            ->select('return_pembelian_items.alasan', DB::raw('COUNT(*) as jumlah'))
            ->groupBy('return_pembelian_items.alasan')
            ->orderByDesc('jumlah')
            ->limit(5)
            ->get();

        // Produk paling sering diretur
        $topProducts = (clone $baseQuery)
            ->join('products', 'return_pembelian_items.product_id', '=', 'products.id')
            ->select('products.name', DB::raw('SUM(return_pembelian_items.qty) as total_qty'))
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        return response()->json([
            'summary' => [
                'jumlah_transaksi' => (int) $summary->jumlah_transaksi,
                'total_retur' => (float) $summary->total_retur,
                'total_barang' => (float) $summary->total_barang,
                'top_alasan' => $topAlasan,
                'top_products' => $topProducts,
            ]
        ]);
        }
}
