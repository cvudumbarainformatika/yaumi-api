<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class SupplierController extends Controller
{
    public function index(): JsonResponse
    {
        $suppliers = Cache::rememberForever('suppliers', function () {
            return Supplier::all();
        });
        return response()->json($suppliers);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'address' => 'nullable|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'description' => 'nullable|string'
        ]);

        $supplier = Supplier::create($validated);
        Cache::forget('suppliers');
        return response()->json($supplier, 201);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        return response()->json($supplier);
    }

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string',
            'address' => 'nullable|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'description' => 'nullable|string'
        ]);

        $supplier->update($validated);
        Cache::forget('suppliers');
        return response()->json($supplier);
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $supplier->delete();
        Cache::forget('suppliers');
        return response()->json(null, 204);
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'nullable|string',
            'sort_by' => 'nullable|in:name,email,phone,created_at',
            'sort_dir' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        $query = $validated['q'] ?? '';
        $perPage = $validated['per_page'] ?? 10;
        
        $searchQuery = Supplier::search($query);

        if (!empty($validated['sort_by'])) {
            $direction = $validated['sort_dir'] ?? 'asc';
            $searchQuery->orderBy($validated['sort_by'], $direction);
        }

        $results = $searchQuery->paginate($perPage);
        return response()->json($results);
    }
}