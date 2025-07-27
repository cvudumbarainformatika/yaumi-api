<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductStockMutation;
use App\Models\StockOpname;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page = (int) request('page', 1); // default halaman 1
        $perPage = (int) request('per_page', 10);
        $offset = ($page - 1) * $perPage;

        $query = Product::query();

        // $query
        //     ->leftJoin('latest_stock_per_product as lsp', 'products.id', '=', 'lsp.product_id')
        //     ->leftJoin('satuans', 'products.satuan_id', '=', 'satuans.id')
        //     ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
        //     ->addSelect([
        //         'products.*','categories.name as category_name', 'satuans.name as satuan_name',
        //         DB::raw('COALESCE(lsp.stock, products.stock) AS stock_akhir')
        //     ]);
        $query->withStockInfo();
        $query->with(['category', 'satuan']);

        if ($request->filled('q') && !empty($request->q)) {
            $search = $request->q;
            $query->where(function($q) use ($search) {
                $q->where('products.barcode', 'like', "%{$search}%")
                    ->orWhere('products.name', 'like', "%{$search}%")
                    ->orWhere('products.rak', 'like', "%{$search}%")
                    ->orWhere('categories.name', 'like', "%{$search}%")
                    ->orWhere('satuans.name', 'like', "%{$search}%");
            });
        }

        // Filter berdasarkan customer_id
        if ($request->filled('status') && !empty($request->status)) {

            $query->when($request->status, function ($query) use ($request) {
                return match ($request->status) {
                    'in-stock' => $query->where(DB::raw('COALESCE(lsp.stock, products.stock)'), '>', DB::raw('products.minstock')),
                    'low-stock' => $query->whereBetween(
                        DB::raw('COALESCE(lsp.stock, products.stock)'), [1, DB::raw('products.minstock')]
                    ),
                    'out-of-stock' => $query->where(DB::raw('COALESCE(lsp.stock, products.stock)'), '<=', 0),
                    default => $query
                };
            });
        }
        
        $totalCount = (clone $query)->count();
        $result = $query->simplePaginate($perPage);

        $data = [
            'data' => $result->items(),
            'meta' => [
                'first' => $result->url(1),
                'last' => url()->current() . '?page=' . ceil($totalCount / $perPage),
                'prev' => $result->previousPageUrl(),
                'next' => $result->nextPageUrl(),
                'current_page' => $result->currentPage(),
                'per_page' => (int)$perPage,
                'total' => (int)$totalCount,
                'last_page' => ceil($totalCount / $perPage),
                'from' => (($result->currentPage() - 1) * $perPage) + 1,
                'to' => min($result->currentPage() * $perPage, $totalCount),
            ],
        ];

        return response()->json($data);
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

    public function update(Request $request, $id): JsonResponse
    {
        // return response()->json($request->all());
        $validated = $request->validate([
            'barcode' => 'sometimes|unique:products,barcode,' . $id,
            'category_id' => 'sometimes|exists:categories,id',
            'name' => 'sometimes|string',
            'satuan_id' => 'sometimes|exists:satuans,id',
            'hargabeli' => 'sometimes|numeric',
            'hargajual' => 'sometimes|numeric',
            'hargajualcust' => 'sometimes|numeric',
            'hargajualantar' => 'sometimes|numeric',
            'stock' => 'sometimes|integer',
            'minstock' => 'sometimes|integer',
            'rak' => 'sometimes|nullable|string',
        ]);

        $product = Product::findOrFail($id);

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        if (!$request->all()) {
            return response()->json(['message' => 'Tidak ada data yang dikirim'], 422);
        }
        $updated = $product->update($validated);

        if (!$updated) {
            return response()->json(['message' => 'Product Gagal Diupdate !'], 404);
        }

        $product = Product::withStockInfo()->with(['category', 'satuan'])->find($product->id);

        return response()->json($product);
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();
        return response()->json(null, 204);
    }

    

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'satuan_id' => 'nullable|exists:satuans,id',
            'status' => 'nullable|in:all,in-stock,low-stock,out-of-stock',  // Add this line
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort_by' => 'nullable|in:name,hargajual,stock,created_at',
            'sort_dir' => 'nullable|in:asc,desc',
        ]);

        $perPage = $validated['per_page'] ?? 12;
        $q = $validated['q'] ?? '';

        $products = Product::search($q, function ($meilisearch, $query, $options) use ($validated) {
            $options['filter'] = [];

            if (!empty($validated['category_id'])) {
                $options['filter'][] = "category_id = {$validated['category_id']}";
            }

            if (!empty($validated['satuan_id'])) {
                $options['filter'][] = "satuan_id = {$validated['satuan_id']}";
            }

            // Add stock status filter
            // if (!empty($validated['status'])) {
            //     switch ($validated['status']) {
            //         case 'in-stock':
            //             $options['filter'][] = "stock > 0";
            //             break;
            //         case 'low-stock':
            //             $options['filter'][] = "is_low_stock = 'true'";
            //             break;
            //         case 'out-of-stock':
            //             $options['filter'][] = "stock = 0";
            //             break;
            //     }
            // }

            if (!empty($validated['sort_by'])) {
                $direction = $validated['sort_dir'] ?? 'asc';
                $options['sort'] = ["{$validated['sort_by']}:{$direction}"];
            }

            $options['attributesToHighlight'] = ['name', 'barcode','category.name','category_name'];

            return $meilisearch->search($query, $options);
        })
        ->query(function ($builder) {
            $builder->with(['category', 'satuan'])
            ->leftJoin('latest_stock_per_product as lsp', 'products.id', '=', 'lsp.product_id')
            ->addSelect([
            'products.*',
            DB::raw('COALESCE(lsp.stock, products.stock) AS stock_akhir')]);
        })
        ->paginate($perPage);



        return response()->json([
            'data' => $products->items(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'last_page' => $products->lastPage(),
            ],
            'links' => [
                'first' => $products->url(1),
                'last' => $products->url($products->lastPage()),
                'prev' => $products->previousPageUrl(), 
                'next' => $products->nextPageUrl(),
            ],
            'filters' => array_filter($validated),
        ]);
    }

    public function mutations(Request $request, $id)
    {
       $page = (int) request('page', 1); // default halaman 1
        $perPage = (int) request('per_page', 10);
        $offset = ($page - 1) * $perPage;

        $query = ProductStockMutation::query();

        $query->where('product_id', $id);

        $query->select(
            'product_stock_mutations.*',
            'products.name as product_name',
            'satuans.name as satuan_name',)
            ->leftJoin('products', 'product_stock_mutations.product_id', '=', 'products.id')
            ->leftJoin('satuans', 'products.satuan_id', '=', 'satuans.id');

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('product_stock_mutations.created_at', [$request->start_date, $request->end_date]);
        }

        // $productStockMutations = $query->orderByDesc('product_stock_mutations.id');
        $totalCount = (clone $query)->count();
        $result = $query->simplePaginate($perPage, ['*'], 'page', $page);
        $data = [
            'data' => $result->items(),
            'meta' => [
                'first' => $result->url(1),
                'last' => url()->current() . '?page=' . ceil($totalCount / $perPage),
                'prev' => $result->previousPageUrl(),
                'next' => $result->nextPageUrl(),
                'current_page' => $result->currentPage(),
                'per_page' => (int)$perPage,
                'total' => (int)$totalCount,
                'last_page' => ceil($totalCount / $perPage),
                'from' => (($result->currentPage() - 1) * $perPage) + 1,
                'to' => min($result->currentPage() * $perPage, $totalCount),
            ],
        ];

        return response()->json($data);
    }


    public function stockOpname(Request $request)
    {
       $product_id = $request->id;

        $catatan = 'Penyesuaian Stock';

        DB::beginTransaction();
        try {

            $selisih = $request->selisih;
            if ($selisih !== 0) {
                StockOpname::create([
                    'user_id' => Auth::id(),
                    'product_id' => $product_id,
                    'stock_sistem'=> $request->stock_akhir,
                    'stock_fisik'=> $request->stock_fisik,
                    'selisih'=> $request->selisih,
                    'catatan'=> $catatan
                ]);
            }
            DB::commit();
            return response()->json(['message' => 'Stock opname berhasil disimpan'], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal menyimpan pembayaran hutang', 'error' => $e->getMessage()], 500);
        }

    }
}
