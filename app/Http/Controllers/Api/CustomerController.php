<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

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
                ->orWhere('email', 'like', "%{$query}%")
                ->orWhere('phone', 'like', "%{$query}%")
                ->orWhere('address', 'like', "%{$query}%");
        }
        
        // Apply sorting
        $customersQuery->orderBy($sortBy, $sortDirection);
        
        // Muat relasi piutang
        $customersQuery->with('receivable');
        
        // Get paginated results
        $customers = $customersQuery->paginate($perPage, ['*'], 'page', $page);
        
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

    public function update(Request $request, Customer $customer): JsonResponse
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

        // Update data customer
        $customer->update($validated);
        
        // Update atau buat data piutang
        if (!empty($receivableData)) {
            if ($customer->receivable) {
                $customer->receivable->update($receivableData);
            } else {
                $customer->receivable()->create($receivableData);
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
}