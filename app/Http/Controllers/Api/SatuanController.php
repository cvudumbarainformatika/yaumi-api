<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Satuan;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SatuanController extends Controller
{
    public function index(): JsonResponse
    {
        $satuans = Satuan::all();
        return response()->json($satuans);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:satuans',
            'description' => 'nullable|string'
        ]);

        $satuan = Satuan::create($validated);
        return response()->json($satuan, 201);
    }

    public function show(Satuan $satuan): JsonResponse
    {
        return response()->json($satuan);
    }

    public function update(Request $request, Satuan $satuan): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|unique:satuans,name,' . $satuan->id,
            'description' => 'nullable|string'
        ]);

        $satuan->update($validated);
        return response()->json($satuan);
    }

    public function destroy(Satuan $satuan): JsonResponse
    {
        $satuan->delete();
        return response()->json(null, 204);
    }
}