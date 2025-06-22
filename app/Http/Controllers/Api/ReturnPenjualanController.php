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
        ->select('return_penjualans.*', 'customers.name as customer_name')
        ->leftJoin('customers', 'return_penjualans.customer_id', '=', 'customers.id')
        ->with(['customer', 'items.product', 'penjualan']);

        // 🔍 Filter pencarian global
        if ($request->filled('q') && !empty($request->q)) {
            $search = $request->q;

            $query->where(function($q) use ($search) {
                $q->where('return_penjualans.unique_code', 'like', "%{$search}%")
                ->orWhereExists(function ($subquery) use ($search) {
                    $subquery->select(DB::raw(1))
                        ->from('customers')
                        ->whereColumn('customers.id', 'return_penjualans.customer_id')
                        ->where('customers.name', 'like', "%{$search}%");
                });
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
            'tanggal' => 'required|date',
            'customer_id' => 'nullable|exists:customers,id',
            'sales_id' => 'nullable|string',
            'keterangan' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.harga' => 'required|numeric|min:0',
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
                'tanggal' => $data['tanggal'],
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
                    'subtotal' => $item['qty'] * $item['harga'],
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
}
