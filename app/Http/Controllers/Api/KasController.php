<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\Kas;
use Illuminate\Http\Request;

class KasController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $kas = Kas::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $q->where('nama', 'like', '%' . $request->q . '%');
            })
            ->orderBy('nama')
            ->get();

        return response()->json($kas);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'tipe' => 'required|in:kasir,bank,pusat',
            'bank_name' => 'nullable|string|max:255',
            'no_rekening' => 'nullable|string|max:100',
            'saldo_awal' => 'required|numeric|min:0',
        ]);

        $kas = Kas::create($validated);

        return response()->json([
            'message' => 'Kas berhasil ditambahkan.',
            'data' => $kas,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $kas = Kas::findOrFail($id);
        return response()->json($kas);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Kas $kas)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $kas = Kas::findOrFail($id);

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'tipe' => 'required|in:kasir,bank,pusat',
            'bank_name' => 'nullable|string|max:255',
            'no_rekening' => 'nullable|string|max:100',
            'saldo_awal' => 'required|numeric|min:0',
        ]);

        $kas->update($validated);

        return response()->json([
            'message' => 'Kas berhasil diperbarui.',
            'data' => $kas,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $kas = Kas::findOrFail($id);
        $kas->delete();

        return response()->json([
            'message' => 'Kas berhasil dihapus.'
        ]);
    }
}
