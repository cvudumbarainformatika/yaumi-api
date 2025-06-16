<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class SupplierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $query = $request->input('q', '');

        $suppliersQuery = Supplier::query();

        // Apply search if query parameter exists
        if (!empty($query)) {
            $suppliersQuery->where('name', 'like', "%{$query}%")
                ->orWhere('phone', 'like', "%{$query}%");
        }

        // Apply sorting
        $suppliersQuery->orderBy($sortBy, $sortDirection);

        // Muat relasi hutang
        $suppliersQuery->with('debt');

        // Get paginated results
        $suppliers = $suppliersQuery->simplePaginate($perPage, ['*'], 'page', $page);

        return response()->json($suppliers);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'address' => 'nullable|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'description' => 'nullable|string',
            'initial_amount' => 'nullable|numeric|min:0',
            'debt_notes' => 'nullable|string'
        ]);

        // Ekstrak data hutang dari input yang divalidasi
        $debtData = [
            'initial_amount' => $validated['initial_amount'] ?? 0,
            'current_amount' => $validated['initial_amount'] ?? 0,
            'notes' => $validated['debt_notes'] ?? null,
        ];

        // Hapus field hutang dari data supplier
        unset($validated['initial_amount'], $validated['debt_notes']);

        // Buat supplier
        $supplier = Supplier::create($validated);

        // Buat catatan hutang terkait
        $supplier->debt()->create($debtData);

        // Muat relasi hutang untuk respons
        $supplier->load('debt');

        Cache::forget('suppliers');
        return response()->json($supplier, 201);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        // Muat relasi hutang untuk menampilkan data lengkap
        $supplier->load('debt');
        return response()->json($supplier);
    }

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string',
            'address' => 'nullable|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'description' => 'nullable|string',
            'initial_amount' => 'nullable|numeric|min:0',
            'current_amount' => 'nullable|numeric|min:0',
            'debt_notes' => 'nullable|string'
        ]);

        // Ekstrak data hutang dari input yang divalidasi
        $debtData = [];
        if (isset($validated['initial_amount'])) {
            $debtData['initial_amount'] = $validated['initial_amount'];
            unset($validated['initial_amount']);
        }

        if (isset($validated['current_amount'])) {
            $debtData['current_amount'] = $validated['current_amount'];
            unset($validated['current_amount']);
        }

        if (isset($validated['debt_notes'])) {
            $debtData['notes'] = $validated['debt_notes'];
            unset($validated['debt_notes']);
        }

        // Update data supplier
        $supplier->update($validated);

        // Update atau buat data hutang
        if (!empty($debtData)) {
            if ($supplier->debt) {
                $supplier->debt->update($debtData);
            } else {
                $supplier->debt()->create($debtData);
            }
        }

        // Muat relasi hutang untuk respons
        $supplier->load('debt');

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
