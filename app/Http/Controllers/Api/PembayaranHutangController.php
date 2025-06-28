<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PembayaranHutang;
use App\Models\PembayaranHutangDetail;
use App\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PembayaranHutangController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 15);
        $offset = ($page - 1) * $perPage;

        // Modular base query
        $buildBaseQuery = function () use ($request) {
            return PembayaranHutang::query()
                ->join('pembayaran_hutang_details', 'pembayaran_hutang_details.pembayaran_hutang_id', '=', 'pembayaran_hutangs.id')
                ->join('purchases', 'pembayaran_hutang_details.purchase_id', '=', 'purchases.id')
                ->join('suppliers', 'purchases.supplier_id', '=', 'suppliers.id')
                ->join('users', 'pembayaran_hutangs.user_id', '=', 'users.id')
                ->when($request->filled('q'), function ($q) use ($request) {
                    $q->where('suppliers.name', 'like', "%{$request->q}%");
                })
                ->when($request->filled('start_date') && $request->filled('end_date'), function ($q) use ($request) {
                    $q->whereBetween('pembayaran_hutangs.tanggal', [$request->start_date, $request->end_date]);
                })
                ->groupBy('pembayaran_hutangs.id', 'suppliers.name');
        };

        // Hitung total count via subquery
        $countBuilder = $buildBaseQuery()
            ->selectRaw('pembayaran_hutangs.id');

        $totalCount = DB::table(DB::raw("({$countBuilder->toSql()}) as temp_table"))
            ->mergeBindings($countBuilder->getQuery())
            ->count();

        // Ambil data
        $dataQuery = $buildBaseQuery()
            ->select('pembayaran_hutangs.*', 'suppliers.name as supplier_name', 'users.name as user_name')
            ->with(['supplier', 'details.purchase.phd'])
            ->orderByDesc('pembayaran_hutangs.created_at')
            ->offset($offset)
            ->limit($perPage);

        $pembayaranHutang = $dataQuery->get();

        return response()->json([
            'data' => $pembayaranHutang,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $totalCount,
                'last_page' => ceil($totalCount / $perPage),
                'from' => $offset + 1,
                'to' => $offset + $pembayaranHutang->count(),
                'prev' => $page > 1 ? $page - 1 : null,
                'next' => ($offset + $pembayaranHutang->count()) < $totalCount ? $page + 1 : null,
            ]
        ]);
    }

    /**
     * Show the search
     */
    public function search(Request $request)
    {
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 15);
        $offset = ($page - 1) * $perPage;

        // Modular base query
        $buildBaseQuery = function () use ($request) {
            return Purchase::query()
                ->join('suppliers', 'purchases.supplier_id', '=', 'suppliers.id')
                ->leftJoin('pembayaran_hutang_details as phd', 'phd.purchase_id', '=', 'purchases.id')
                ->where('purchases.payment_method', 'credit')
                ->when($request->filled('q'), function ($q) use ($request) {
                    $q->where('suppliers.name', 'like', "%{$request->q}%");
                })
                ->when($request->filled('start_date') && $request->filled('end_date'), function ($q) use ($request) {
                    $q->whereBetween('purchases.tanggal', [$request->start_date, $request->end_date]);
                })
                ->groupBy('purchases.id', 'suppliers.name', 'purchases.unique_code', 'purchases.created_at');
        };

        // Total count via subquery
        $countBuilder = $buildBaseQuery()
            ->selectRaw('purchases.id, (purchases.total - COALESCE(SUM(phd.dibayar), 0)) as saldo')
            ->havingRaw('saldo > 0');

        $totalCount = DB::table(DB::raw("({$countBuilder->toSql()}) as temp_table"))
            ->mergeBindings($countBuilder->getQuery())
            ->count();

        // Data query
        $dataQuery = $buildBaseQuery()
            ->select('purchases.*', 'suppliers.name as supplier_name')
            ->selectRaw('COALESCE(SUM(phd.dibayar), 0) as total_dibayar')
            ->selectRaw('(purchases.total - COALESCE(SUM(phd.dibayar), 0)) as saldo')
            ->with(['supplier', 'phd.pembayaran'])
            ->havingRaw('saldo > 0')
            ->orderByDesc('purchases.created_at')
            ->offset($offset)
            ->limit($perPage);

        $purchases = $dataQuery->get()->map(function ($item) {
            $item->status = $item->saldo <= 0
                ? 'lunas'
                : ($item->total_dibayar > 0 ? 'sebagian' : 'belum lunas');
            return $item;
        });

        return response()->json([
            'data' => $purchases,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $totalCount,
                'last_page' => ceil($totalCount / $perPage),
                'from' => $offset + 1,
                'to' => $offset + $purchases->count(),
                'prev' => $page > 1 ? $page - 1 : null,
                'next' => ($offset + $purchases->count()) < $totalCount ? $page + 1 : null,
            ]
        ]);
    
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'total' => 'required|numeric|min:0',
            'metode_pembayaran' => 'required|in:tunai,transfer,giro,qris',
            'keterangan' => 'nullable|string',
            'rekening_tujuan' => 'nullable|string',
            'bank_tujuan' => 'nullable|string',
            'nama_rekening' => 'nullable|string',
            'nomor_giro' => 'nullable|string',
            'tanggal_jatuh_tempo' => 'nullable|date',
            'purchase_id' => 'nullable|exists:purchases,id',
            'dibayar' => 'required|numeric|min:0'
        ]);

        DB::beginTransaction();
        try {
            $userId = Auth::id();
            $tanggal = date('Y-m-d H:i:s');

            $pembayaran = PembayaranHutang::create([
                ...$data,
                'user_id' => $userId,
                'tanggal' => $tanggal
            ]);

            PembayaranHutangDetail::create([
                'pembayaran_hutang_id' => $pembayaran->id,
                'purchase_id' => $data['purchase_id'],
                'dibayar' => $data['dibayar']
            ]);

            // foreach ($data['details'] ?? [] as $item) {
            //     $pembayaran->details()->create($item);
            // }

            // Catat ke supplier_debt_histories
            // $debt = SupplierDebt::where('supplier_id', $data['supplier_id'])->firstOrFail();
            // $before = $debt->saldo;
            // $after = $before - $data['total'];

            // $debt->update(['saldo' => $after]);

            // SupplierDebtHistory::create([
            //     'supplier_debt_id' => $debt->id,
            //     'mutation_type' => 'decrease',
            //     'amount' => $data['total'],
            //     'balance_before' => $before,
            //     'balance_after' => $after,
            //     'source_type' => 'pembayaran_hutang',
            //     'source_id' => $pembayaran->id,
            //     'notes' => $data['keterangan'] ?? null
            // ]);

            DB::commit();
            return response()->json(['message' => 'Pembayaran hutang berhasil disimpan']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal menyimpan pembayaran hutang', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(PembayaranHutang $pembayaranHutang)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PembayaranHutang $pembayaranHutang)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PembayaranHutang $pembayaranHutang)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PembayaranHutang $pembayaranHutang)
    {
        //
    }
}
