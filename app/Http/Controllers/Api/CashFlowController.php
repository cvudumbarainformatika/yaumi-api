<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\CashFlow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CashFlowController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 15);
        $offset = ($page - 1) * $perPage;

        $query = CashFlow::with(['kas', 'kasir', 'user']);

        if ($request->filled('tipe')) {
            $query->where('tipe', $request->tipe); // masuk / keluar
        }

        if ($request->filled('kas_id')) {
            $query->where('kas_id', $request->kas_id);
        }

        if ($request->filled('kasir_id')) {
            $query->where('kasir_id', $request->kasir_id);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->when($request->filled('start_date') && $request->filled('end_date'), function ($q) use ($request) {
                $start = $request->start_date . ' 00:00:00';
                $end = $request->end_date . ' 23:59:59';
                $q->whereBetween('cash_flows.created_at', [$start, $end]);
            });
        }

       
        $query->orderByDesc('cash_flows.tanggal');

        $total = (clone $query)->count();

        $data = $query
            ->offset($offset)
            ->limit($perPage)
            ->get();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
                'from' => $offset + 1,
                'to' => $offset + $data->count(),
                'prev' => $page > 1 ? $page - 1 : null,
                'next' => ($offset + $data->count()) < $total ? $page + 1 : null,
            ]
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            // 'tanggal'     => 'required|date',
            'tipe'        => 'required|in:in,out',
            'kas_id'      => 'required|exists:kas,id',
            'kasir_id'    => 'nullable|exists:users,id', // hanya jika tipe kas = kasir
            'jumlah'      => 'required|numeric|min:0',
            'keterangan'  => 'nullable|string|max:1000',
            'source_type' => 'nullable|string|max:100',
            'source_id'   => 'nullable|integer',
        ]);

        $validated['user_id'] = Auth::id(); // atau isi manual jika belum pakai auth
        $validated['tanggal'] = date('Y-m-d H:i:s');
        $validated['kategori'] = $validated['tipe'] ;

        $cashFlow = CashFlow::create($validated);

        return response()->json([
            'message' => 'Arus kas berhasil dicatat.',
            'data'    => $cashFlow->load(['kas', 'kasir', 'user']),
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(CashFlow $cashFlow)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(CashFlow $cashFlow)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, CashFlow $cashFlow)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CashFlow $cashFlow)
    {
        //
    }
}
