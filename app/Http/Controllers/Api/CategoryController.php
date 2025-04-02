<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;  // Add this at the top with other uses

class CategoryController extends Controller
{
    private string $cacheKey = 'laravel_categories';

    public function index(): JsonResponse
    {
        Log::info('Attempting to fetch categories from cache');

        $categories = Cache::remember($this->cacheKey, 3600, function () {
            Log::info('Cache miss - fetching from database');
            return Category::all();
        });

        Log::info('Cache status', [
            'exists' => Cache::has($this->cacheKey),
            'data_count' => $categories->count()
        ]);

        return response()->json($categories);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:categories',
            'description' => 'nullable|string'
        ]);

        $category = Category::create($validated);
        Cache::forget($this->cacheKey);
        return response()->json($category, 201);
    }

    public function show(Category $category): JsonResponse
    {
        return response()->json($category);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|unique:categories,name,' . $category->id,
            'description' => 'nullable|string'
        ]);

        $category->update($validated);
        Cache::forget($this->cacheKey);
        return response()->json($category);
    }

    public function destroy(Category $category): JsonResponse
    {
        $category->delete();
        Cache::forget($this->cacheKey);
        return response()->json(null, 204);
    }
}
