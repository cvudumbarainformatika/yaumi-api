<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\PembayaranPiutang;
use App\Models\PembayaranPiutangDetail;
use App\Models\Sales;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PembayaranPiutangController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 15);
        $offset = ($page - 1) * $perPage;

        // Base query closure
        $buildBaseQuery = function () use ($request) {
            return PembayaranPiutang::query()
                ->join('pembayaran_piutang_details', 'pembayaran_piutang_details.pembayaran_piutang_id', '=', 'pembayaran_piutangs.id')
                ->join('sales', 'pembayaran_piutang_details.sale_id', '=', 'sales.id')
                ->join('customers', 'sales.customer_id', '=', 'customers.id')
                ->when($request->filled('q'), function ($q) use ($request) {
                    $q->where('customers.name', 'like', '%' . $request->q . '%');
                })
                ->when($request->filled('start_date') && $request->filled('end_date'), function ($q) use ($request) {
                    $q->whereBetween('pembayaran_piutangs.tanggal', [$request->start_date, $request->end_date]);
                })
                ->groupBy('pembayaran_piutangs.id', 'customers.name');
        };

        // Hitung total count via subquery
        $countBuilder = $buildBaseQuery()->selectRaw('pembayaran_piutangs.id');

        $totalCount = DB::table(DB::raw("({$countBuilder->toSql()}) as temp_table"))
            ->mergeBindings($countBuilder->getQuery())
            ->count();

        // Ambil data
        $dataQuery = $buildBaseQuery()
            ->select('pembayaran_piutangs.*', 'customers.name as customer_name')
            ->orderByDesc('pembayaran_piutangs.created_at')
            ->offset($offset)
            ->limit($perPage);

        $pembayaranPiutang = $dataQuery->get();

        return response()->json([
            'data' => $pembayaranPiutang,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $totalCount,
                'last_page' => ceil($totalCount / $perPage),
                'from' => $offset + 1,
                'to' => $offset + $pembayaranPiutang->count(),
                'prev' => $page > 1 ? $page - 1 : null,
                'next' => ($offset + $pembayaranPiutang->count()) < $totalCount ? $page + 1 : null,
            ]
        ]);
    }


    /**
     * Show the form for creating a new resource.
     */
    public function search(Request $request)
    {
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 15);
        $offset = ($page - 1) * $perPage;

        // Modular base query
        $buildBaseQuery = function () use ($request) {
            return Sales::query()
                ->join('customers', 'sales.customer_id', '=', 'customers.id')
                ->leftJoin('pembayaran_piutang_details as ppd', 'ppd.sale_id', '=', 'sales.id')
                ->where('sales.payment_method', 'credit')
                ->when($request->filled('q'), function ($q) use ($request) {
                    $q->where('customers.name', 'like', "%{$request->q}%");
                })
                ->when($request->filled('start_date') && $request->filled('end_date'), function ($q) use ($request) {
                    $q->whereBetween('sales.created_at', [$request->start_date, $request->end_date]);
                })
                ->groupBy('sales.id', 'customers.name', 'sales.unique_code', 'sales.created_at');
        };

        // Total count via subquery
        $countBuilder = $buildBaseQuery()
            ->selectRaw('sales.id, (sales.total - COALESCE(SUM(ppd.dibayar), 0)) as saldo')
            ->havingRaw('saldo > 0');

        $totalCount = DB::table(DB::raw("({$countBuilder->toSql()}) as temp_table"))
            ->mergeBindings($countBuilder->getQuery())
            ->count();

        // Data query
        $dataQuery = $buildBaseQuery()
            ->select('sales.*', 'customers.name as customer_name')
            ->selectRaw('COALESCE(SUM(ppd.dibayar), 0) as total_dibayar')
            ->selectRaw('(sales.total - COALESCE(SUM(ppd.dibayar), 0)) as saldo')
            ->with(['customer', 'ppd.pembayaran'])
            ->havingRaw('saldo > 0')
            ->orderByDesc('sales.created_at')
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
        $validated = $request->validate([
        'customer_id' => 'required|exists:customers,id',
        'total' => 'required|numeric|min:0',
        'metode_pembayaran' => 'required|in:tunai,transfer,giro,qris',
        'keterangan' => 'nullable|string',
        'bank_tujuan' => 'nullable|string',
        'rekening_tujuan' => 'nullable|string',
        'nama_rekening' => 'nullable|string',
        'nomor_giro' => 'nullable|string',
        'tanggal_jatuh_tempo' => 'nullable|date',
        'sale_id' => 'required|exists:sales,id',
        'dibayar' => 'required|numeric|min:0',
    ]);

    DB::beginTransaction();
    try {
        $pembayaran = PembayaranPiutang::create([
            'customer_id' => $validated['customer_id'],
            'tanggal' => date('Y-m-d H:i:s'),
            'total' => $validated['total'],
            'metode_pembayaran' => $validated['metode_pembayaran'],
            'bank_tujuan' => $validated['bank_tujuan'] ?? null,
            'rekening_tujuan' => $validated['rekening_tujuan'] ?? null,
            'nama_rekening' => $validated['nama_rekening'] ?? null,
            'nomor_giro' => $validated['nomor_giro'] ?? null,
            'tanggal_jatuh_tempo' => $validated['tanggal_jatuh_tempo'] ?? null,
            'keterangan' => $validated['keterangan'] ?? null,
            'user_id' => Auth::id(),
        ]);

        PembayaranPiutangDetail::create([
            'pembayaran_piutang_id' => $pembayaran->id,
            'sale_id' => $validated['sale_id'],
            'dibayar' => $validated['dibayar'],
        ]);

        DB::commit();

        return response()->json([
            'message' => 'Pembayaran piutang berhasil disimpan',
            'data' => $pembayaran->load('customer', 'details.sale')
        ]);
    } catch (\Throwable $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Gagal menyimpan pembayaran piutang',
            'error' => $e->getMessage()
        ], 500);
    }
    }

    /**
     * Display the specified resource.
     */
    public function show(PembayaranPiutang $pembayaranPiutang)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PembayaranPiutang $pembayaranPiutang)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PembayaranPiutang $pembayaranPiutang)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PembayaranPiutang $pembayaranPiutang)
    {
        //
    }
}
