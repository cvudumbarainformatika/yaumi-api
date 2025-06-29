<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReturnPenjualan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReturnPenjualanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // return ReturnPenjualan::with('customer', 'items.product', 'penjualan')->latest()->simplePaginate(20);
        $query = ReturnPenjualan::query()
        ->select('return_penjualans.*', 'customers.name as customer_name', 'users.name as user_name')
        ->leftJoin('customers', 'return_penjualans.customer_id', '=', 'customers.id')
        ->leftJoin('users', 'return_penjualans.user_id', '=', 'users.id')
        ->with(['customer', 'items.product', 'penjualan']);

        // 🔍 Filter pencarian global
        if ($request->filled('q') && !empty($request->q)) {
            $search = $request->q;

            $query->where(function($q) use ($search) {
                $q->where('return_penjualans.unique_code', 'like', "%{$search}%")
                ->orWhere('customers.name', 'like', "%{$search}%");
            });
        }

        // 🧾 Filter berdasarkan metode pembayaran (jika ada)
        // if ($request->filled('status') && $request->status !== 'semua') {
        //     $query->where('return_penjualans.payment_method', $request->status); // jika kamu menyimpan metode pembayaran di sini
        // }

        // 📅 Filter tanggal
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('return_penjualans.tanggal', [
                $request->start_date, $request->end_date
            ]);
        }

        // 📄 Pagination
        $perPage = $request->input('per_page', 10);
        $totalCount = (clone $query)->count();
        $returns = $query->latest('return_penjualans.created_at')->simplePaginate($perPage);

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
            'nota' => 'nullable|string',
            'customer_id' => 'nullable',
            'sales_id' => 'nullable',
            'keterangan' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.harga' => 'required|numeric|min:0',
            'items.*.subtotal' => 'required|numeric|min:0',
            'items.*.status' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $kode = 'RTJ-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(4));

            $total = collect($data['items'])->sum(function ($item) {
                return $item['qty'] * $item['harga'];
            });

            $retur = ReturnPenjualan::create([
                'unique_code' => $kode,
                'nota' => $data['nota'] ?? null,
                'tanggal' => date('Y-m-d H:i:s'),
                'customer_id' => $data['customer_id'],
                'sales_id' => $data['sales_id'],
                'user_id' => Auth::id(),
                'keterangan' => $data['keterangan'] ?? null,
                'total' => $total
            ]);

            foreach ($data['items'] as $item) {
                $retur->items()->create([
                    'product_id' => $item['product_id'],
                    'qty' => $item['qty'],
                    'harga' => $item['harga'],
                    'subtotal' => $item['subtotal'],
                    'status'=> $item['status']
                ]);
            }

            DB::commit();

            return response()->json(['message' => 'Return penjualan berhasil disimpan.', 'data' => $retur->load('customer', 'items.product', 'penjualan')]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal menyimpan retur.', 'error' => $th->getMessage()], 500);
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

        $query = ReturnPenjualan::query()
            ->select('return_penjualans.*', 'customers.name as customer_name', 'users.name as user_name')
            ->leftJoin('customers', 'return_penjualans.customer_id', '=', 'customers.id')
            ->leftJoin('users', 'return_penjualans.user_id', '=', 'users.id')
            ->with(['items.product']) // preload relasi item + produk
            ->when($request->filled('q'), function ($q) use ($request) {
                $search = $request->q;
                $q->where(function ($sub) use ($search) {
                    $sub->where('return_penjualans.unique_code', 'like', "%$search%")
                        ->orWhere('customers.name', 'like', "%$search%")
                        ->orWhere('users.name', 'like', "%$search%");
                });
            })
            ->when($request->filled('start_date') && $request->filled('end_date'), function ($q) use ($request) {
                $q->whereBetween('return_penjualans.tanggal', [$request->start_date, $request->end_date]);
            })
            ->orderByDesc('return_penjualans.tanggal');

        $totalCount = (clone $query)->count();

        $data = $query
            ->offset($offset)
            ->limit($perPage)
            ->get()
            ->map(function ($item) {
                $item->jumlah_barang = $item->items->sum('qty');
                return $item;
            });

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
        $baseQuery = ReturnPenjualan::query()
        ->leftJoin('return_penjualan_items', 'return_penjualans.id', '=', 'return_penjualan_items.return_penjualan_id');

        // Filter by search
        if ($request->filled('q')) {
            $search = $request->q;
            $baseQuery->leftJoin('customers', 'return_penjualans.customer_id', '=', 'customers.id')
                ->leftJoin('users', 'return_penjualans.user_id', '=', 'users.id')
                ->where(function ($q) use ($search) {
                    $q->where('return_penjualans.unique_code', 'like', "%$search%")
                    ->orWhere('customers.name', 'like', "%$search%")
                    ->orWhere('users.name', 'like', "%$search%");
                });
        }

        // Filter by date
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $baseQuery->whereBetween('return_penjualans.tanggal', [$request->start_date, $request->end_date]);
        }

        // Summary utama
        $summary = (clone $baseQuery)->selectRaw('
            COUNT(DISTINCT return_penjualans.id) as jumlah_transaksi,
            SUM(return_penjualans.total) as total_retur,
            SUM(return_penjualan_items.qty) as total_barang
        ')->first();

        // Barang berdasarkan status
        $statusSummary = (clone $baseQuery)
            ->select('return_penjualan_items.status', DB::raw('SUM(return_penjualan_items.qty) as total'))
            ->groupBy('return_penjualan_items.status')
            ->pluck('total', 'status');

        // Produk terbanyak diretur
        $topProducts = (clone $baseQuery)
            ->join('products', 'return_penjualan_items.product_id', '=', 'products.id')
            ->select('products.name', DB::raw('SUM(return_penjualan_items.qty) as total_qty'))
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        return response()->json([
            'summary' => [
                'jumlah_transaksi' => (int) $summary->jumlah_transaksi,
                'total_retur' => (float) $summary->total_retur,
                'total_barang' => (int) $summary->total_barang,
                'per_status' => [
                    'baik' => (int) ($statusSummary['baik'] ?? 0),
                    'rusak' => (int) ($statusSummary['rusak'] ?? 0),
                ],
                'top_products' => $topProducts,
            ]
        ]);
    }
}
