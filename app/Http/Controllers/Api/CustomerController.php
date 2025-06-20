<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $query = $request->input('q', '');

        $customersQuery = Customer::query();

        // Apply search if query parameter exists
        if (!empty($query)) {
            $customersQuery->where('name', 'like', "%{$query}%")
                ->orWhere('phone', 'like', "%{$query}%");
        }

        // Apply sorting
        $customersQuery->orderBy($sortBy, $sortDirection);

        // Muat relasi piutang
        $customersQuery->with('receivable');

        // Get paginated results
        $customers = $customersQuery->simplePaginate($perPage, ['*'], 'page', $page);

        return response()->json($customers);
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
            'receivable_notes' => 'nullable|string'
        ]);

        // Ekstrak data piutang dari input yang divalidasi
        $receivableData = [
            'initial_amount' => $validated['initial_amount'] ?? 0,
            'current_amount' => $validated['initial_amount'] ?? 0,
            'notes' => $validated['receivable_notes'] ?? null,
        ];

        // Hapus field piutang dari data customer
        unset($validated['initial_amount'], $validated['receivable_notes']);

        // Buat customer
        $customer = Customer::create($validated);

        // Buat catatan piutang terkait
        $customer->receivable()->create($receivableData);

        // Muat relasi piutang untuk respons
        $customer->load('receivable');

        return response()->json($customer, 201);
    }

    public function show(Customer $customer): JsonResponse
    {
        // Muat relasi piutang untuk menampilkan data lengkap
        $customer->load('receivable');
        return response()->json($customer);
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
            'receivable_notes' => 'nullable|string'
        ]);


        $customer = Customer::findOrFail($id);

        // return response()->json([
        //     'raw' => $customer,
        //     'array' => $customer->toArray(),
        //     'id' => $customer->id,
        //     'name' => $customer->name,
        // ]);

        // Ekstrak data piutang dari input yang divalidasi
        $receivableData = [];
        if (isset($validated['initial_amount'])) {
            $receivableData['initial_amount'] = $validated['initial_amount'];
            unset($validated['initial_amount']);
        }

        if (isset($validated['current_amount'])) {
            $receivableData['current_amount'] = $validated['current_amount'];
            unset($validated['current_amount']);
        }

        if (isset($validated['receivable_notes'])) {
            $receivableData['notes'] = $validated['receivable_notes'];
            unset($validated['receivable_notes']);
        }

        // Log::info('DEBUG create receivable', [
        //     'customer_id' => $customer->id,
        //     'has_receivable' => (bool) $customer->receivable,
        //     'data' => $receivableData,
        // ]);

        // Update data customer
        $customer->update($validated);



        // Update atau buat data piutang
        if (!empty($receivableData)) {
            if ($customer->receivable) {
                $customer->receivable->update($receivableData);
            } else {
                $customer->receivable()->create(array_merge($receivableData, [
                    'customer_id' => $customer->id,
                ]));
            }
        }

        // Muat relasi piutang untuk respons
        $customer->load('receivable');

        return response()->json($customer);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $customer->delete();
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

        $searchQuery = Customer::search($query);

        if (!empty($validated['sort_by'])) {
            $direction = $validated['sort_dir'] ?? 'asc';
            $searchQuery->orderBy($validated['sort_by'], $direction);
        }

        $results = $searchQuery->paginate($perPage);
        return response()->json($results);
    }
}
