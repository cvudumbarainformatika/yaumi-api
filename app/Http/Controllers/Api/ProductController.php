<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function index(): JsonResponse
    {
        $products = Product::with('category')->paginate(10);
        return response()->json($products);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'barcode' => 'required|unique:products',
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string',
            'satuan_id' => 'required|exists:satuans,id',
            'hargabeli' => 'required|numeric',
            'hargajual' => 'required|numeric',
            'hargajualcust' => 'required|numeric',
            'hargajualantar' => 'required|numeric',
            'stock' => 'required|integer',
            'minstock' => 'required|integer',
            'rak' => 'required|string',
        ]);

        $product = Product::create($validated);
        return response()->json($product, 201);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json($product->load('category'));
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'barcode' => 'sometimes|unique:products,barcode,' . $product->id,
            'category_id' => 'sometimes|exists:categories,id',
            'name' => 'sometimes|string',
            'satuan' => 'sometimes|string',
            'hargabeli' => 'sometimes|numeric',
            'hargajual' => 'sometimes|numeric',
            'hargajualcust' => 'sometimes|numeric',
            'hargajualantar' => 'sometimes|numeric',
            'stock' => 'sometimes|integer',
            'minstock' => 'sometimes|integer',
            'rak' => 'sometimes|string',
        ]);

        $product->update($validated);
        return response()->json($product);
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();
        return response()->json(null, 204);
    }

    // public function search(Request $request): JsonResponse
    // {
    //     $validated = $request->validate([
    //         'q' => 'nullable|string',
    //         'category_id' => 'nullable|exists:categories,id',
    //         'sort_by' => 'nullable|in:name,hargajual,stock,created_at',
    //         'sort_dir' => 'nullable|in:asc,desc',
    //         'per_page' => 'nullable|integer|min:1|max:100',
    //         'stock_op' => 'nullable|in:>,<,=,>=,<=',
    //         'stock_val' => 'nullable|integer',
    //         'hargajual_op' => 'nullable|in:>,<,=,>=,<=',
    //         'hargajual_val' => 'nullable|numeric',
    //         'hargajualcust_op' => 'nullable|in:>,<,=,>=,<=',
    //         'hargajualcust_val' => 'nullable|numeric',
    //         'hargajualantar_op' => 'nullable|in:>,<,=,>=,<=',
    //         'hargajualantar_val' => 'nullable|numeric',
    //     ]);

    //     $query = $validated['q'] ?? '*';
    //     $perPage = $validated['per_page'] ?? 12;

    //     // Build Meilisearch options
    //     $searchOptions = [
    //         'attributesToRetrieve' => ['*'],
    //         'filter' => [],
    //         'limit' => $perPage, // Add limit
    //         'offset' => ($request->get('page', 1) - 1) * $perPage // Add offset for pagination
    //     ];

    //     // Add filters
    //     if (!empty($validated['category_id'])) {
    //         $searchOptions['filter'][] = "category_id = {$validated['category_id']}";
    //     }

    //     if (!empty($validated['stock_op']) && isset($validated['stock_val'])) {
    //         $searchOptions['filter'][] = "stock {$validated['stock_op']} {$validated['stock_val']}";
    //     }

    //     if (!empty($validated['hargajual_op']) && isset($validated['hargajual_val'])) {
    //         $searchOptions['filter'][] = "hargajual {$validated['hargajual_op']} {$validated['hargajual_val']}";
    //     }

    //     if (!empty($validated['sort_by'])) {
    //         $direction = $validated['sort_dir'] ?? 'asc';
    //         $searchOptions['sort'] = ["{$validated['sort_by']}:{$direction}"];
    //     }

    //     $products = Product::search($query, function ($meilisearch, $query, $options) use ($searchOptions) {
    //         return $meilisearch->search($query, $searchOptions);
    //     })
    //     ->within('products')
    //     ->query(function ($builder) {
    //         $builder->with(['category', 'satuan']);
    //     })
    //     ->paginate($perPage);

    //     return response()->json([
    //         'data' => $products->items(),
    //         'meta' => [
    //             'current_page' => $products->currentPage(),
    //             'per_page' => $products->perPage(),
    //             'total' => $products->total(),
    //             'last_page' => $products->lastPage(),
    //         ],
    //         'links' => [
    //             'first' => $products->url(1),
    //             'last' => $products->url($products->lastPage()),
    //             'prev' => $products->previousPageUrl(),
    //             'next' => $products->nextPageUrl(),
    //         ],
    //         'filters' => array_filter($validated),
    //     ]);
    // }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort_by' => 'nullable|in:name,hargajual,stock,created_at',
            'sort_dir' => 'nullable|in:asc,desc',
        ]);

        $perPage = $validated['per_page'] ?? 12;

        $products = Product::search('', function ($meilisearch, $query, $options) use ($validated) {
            if (!empty($validated['category_id'])) {
                $options['filter'] = ["category_id = {$validated['category_id']}"];
            }

            if (!empty($validated['sort_by'])) {
                $direction = $validated['sort_dir'] ?? 'asc';
                $options['sort'] = ["{$validated['sort_by']}:{$direction}"];
            }

            return $meilisearch->search($query, $options);
        })
        ->query(function ($builder) {
            $builder->with(['category', 'satuan']);
        })
        ->paginate($perPage);

        return response()->json([
            'data' => $products->items(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'last_page' => $products->lastPage(),
            ]
        ]);
    }
}
