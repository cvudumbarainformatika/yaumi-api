<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
        // $suppliersQuery->leftJoin('supplier_debts', 'supplier_debts.supplier_id', '=', 'suppliers.id')
        // ->leftJoin('latest_debt_per_supplier as lds', 'supplier_debts.id', '=', 'lds.supplier_debt_id')
        // ->addSelect([
        //     'suppliers.*','supplier_debts.initial_amount as saldo_awal',
        //     DB::raw('COALESCE(lds.balance_after, supplier_debts.initial_amount, 0) as total_hutang')
        // ]);

        $suppliersQuery->withDebtInfo(); // ini ambil scope di Supplier.php
        
        // Apply search if query parameter exists
        if (!empty($query)) {
            $suppliersQuery->where('name', 'like', "%{$query}%")
                ->orWhere('phone', 'like', "%{$query}%");
        }

        // Apply sorting
        $suppliersQuery->orderBy($sortBy, $sortDirection);

        // Muat relasi hutang
        // $suppliersQuery->with('debt');
        
        $totalCount = (clone $suppliersQuery)->count();
        $returns = $suppliersQuery->simplePaginate($perPage);

        // Get paginated results
        // $suppliers = $suppliersQuery->simplePaginate($perPage, ['*'], 'page', $page);

        $data = [
            'data' => $returns->items(),
            'meta' => [
                'first' => $returns->url(1),
                'last' => $returns->url(ceil($totalCount / $perPage)),
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

        // Cache::forget('suppliers');
        return response()->json($supplier, 201);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        // Muat relasi hutang untuk menampilkan data lengkap
        $supplier->load('debt');
        return response()->json($supplier);
    }

    public function update(Request $request, $id): JsonResponse
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

        $supplier = Supplier::findOrFail($id);

        // Ekstrak data hutang dari input yang divalidasi
        $debtData = [];

        // Jika initial_amount diminta
        if (isset($validated['initial_amount'])) {
            $shouldKeepInitialAmount = true;

            // Cek: jika ada hutang & histori, jangan update initial_amount
            if ($supplier->debt && $supplier->debt->histories()->exists()) {
                $shouldKeepInitialAmount = false;
            }

            if ($shouldKeepInitialAmount) {
                $debtData['initial_amount'] = $validated['initial_amount'];
            }

            unset($validated['initial_amount']); // selalu hapus dari $validated
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
        // $supplier->load('debt');
        // Ambil ulang supplier + join + relasi untuk respons akhir
        $supplier = Supplier::withDebtInfo()->with('debt')->findOrFail($supplier->id);

        // Cache::forget('suppliers');
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
