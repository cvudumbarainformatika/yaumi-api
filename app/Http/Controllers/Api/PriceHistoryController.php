<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductPriceHistory;
use Illuminate\Http\Request;

class PriceHistoryController extends Controller
{
    public function index(Request $request)
    {
        $query = ProductPriceHistory::query()
            ->select('product_price_histories.*', 'products.name as product_name', 'users.name as user_name',
               'purchases.invoice_number as invoice_pembelian',
            )
            ->leftJoin('products', 'product_price_histories.product_id', '=', 'products.id')
            ->leftJoin('users', 'product_price_histories.user_id', '=', 'users.id')
            ->leftJoin('purchases', 'product_price_histories.source_id', '=', 'purchases.id');
            

        //     // Filter pencarian jika parameter q tidak kosong
        if ($request->filled('q') && !empty($request->q)) {
            $search = $request->q;
            $query->where(function($q) use ($search) {
                $q->where('products.name', 'like', "%{$search}%")
                  ->orWhere('users.name', 'like', "%{$search}%");
            });
        }

        

       

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('product_price_histories.created_at', [$request->start_date, $request->end_date]);
        }
        $query->orderByDesc('product_price_histories.created_at');
        // $purchases = $query->orderByDesc('purchases.id')->simplePaginate(15);
        $perPage = $request->input('per_page', 10);
        $totalCount = (clone $query)->count();

        // Lakukan pagination dengan simplePaginate
        $result = $query->simplePaginate($perPage);

        $data = [
            'data' => $result->items(),
            'meta' => [
                'first' => $result->url(1),
                'last' => null, // SimplePaginator tidak menyediakan ini
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
   
}
