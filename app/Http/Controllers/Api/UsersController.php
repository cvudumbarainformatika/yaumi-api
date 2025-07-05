<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\Kas;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UsersController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $data = User::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->q . '%');
            })
            ->when($request->filled('role'), function ($q) use ($request) {
                $q->where('role', '=', $request->role);
            })
            ->orderBy('name')
            ->get();

        return response()->json($data);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function list(Request $request)
    {
        
        $query = User::query()
            ->where('role', '!=', 'root')
            ->when($request->filled('q'), function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->q . '%');
            })
            ->when($request->filled('role'), function ($q) use ($request) {
                $q->where('role', '=', $request->role);
            });
        // Sorting
        $sortField = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');

        $query->orderBy($sortField, $sortDirection);
        // $purchases = $query->orderByDesc('purchases.id')->simplePaginate(15);
        $perPage = $request->input('per_page', 10);
        $totalCount = (clone $query)->count();

        // Lakukan pagination dengan simplePaginate
        $users = $query->simplePaginate($perPage);

        $data = [
            'data' => $users->items(),
            'meta' => [
                'first' => $users->url(1),
                'last' => null, // SimplePaginator tidak menyediakan ini
                'prev' => $users->previousPageUrl(),
                'next' => $users->nextPageUrl(),
                'current_page' => $users->currentPage(),
                'per_page' => (int)$perPage,
                'total' => (int)$totalCount,
                'last_page' => ceil($totalCount / $perPage),
                'from' => (($users->currentPage() - 1) * $perPage) + 1,
                'to' => min($users->currentPage() * $perPage, $totalCount),
            ],
        ];

        return response()->json($data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'username' => [
                'required', 'string', 'max:50', 'unique:users,username',
                'regex:/^[a-z0-9]+$/'
            ],
            'email' => 'nullable|email|unique:users,email',
            'password' => 'required|string|min:4|confirmed',
            'role' => 'required|string|in:admin,cashier,owner',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'] ?? null,
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
        ]);

        return response()->json([
            'message' => 'User berhasil dibuat',
            'data' => $user
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        // $kas = Kas::findOrFail($id);
        // return response()->json($kas);
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
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'username' => [
                'required', 'string', 'min:4', 'max:32', 'regex:/^[a-z0-9]+$/',
                Rule::unique('users')->ignore($user->id),
            ],
            'name' => 'required|string|max:255',
            'email' => [
                'nullable', 'email',
                Rule::unique('users')->ignore($user->id),
            ],
            'password' => 'nullable|string|min:6|confirmed',
        ]);

        $user->update([
            'username' => $validated['username'],
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'password' => $validated['password'] ? Hash::make($validated['password']) : $user->password,
        ]);

       return response()->json(['message' => 'User updated', 'user' => $user]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        // $kas = Kas::findOrFail($id);
        // $kas->delete();

        // return response()->json([
        //     'message' => 'Kas berhasil dihapus.'
        // ]);
    }
}
