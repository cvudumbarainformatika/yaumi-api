<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockOpname;
use Illuminate\Http\Request;

class StockOpnameController extends Controller
{
    public function index(Request $request)
    {
        $query = StockOpname::query()
            ->select('stock_opname.*', 'products.name as product_name', 'users.name as user_name','psm.mutation_type',
                'psm.qty as mutation_qty',
                'psm.stock_before',
                'psm.stock_after'
            )
            ->leftJoin('products', 'stock_opname.product_id', '=', 'products.id')
            ->leftJoin('users', 'stock_opname.user_id', '=', 'users.id')
            ->leftJoin('product_stock_mutations as psm', function ($join) {
                    $join->on('stock_opname.id', '=', 'psm.source_id')
                        ->where('psm.source_type', '=', 'stock_opname');
                });

        //     // Filter pencarian jika parameter q tidak kosong
        // if ($request->filled('q') && !empty($request->q)) {
        //     $search = $request->q;
        //     $query->where(function($q) use ($search) {
        //         $q->where('sales.unique_code', 'like', "%{$search}%")
        //           ->orWhere('customers.name', 'like', "%{$search}%");
        //     });
        // }

        // Filter berdasarkan customer_id
        // if ($request->filled('status') && !empty($request->status)) {
        //     if ($request->status !== 'semua') {
        //         $query->where('sales.payment_method', '=', $request->status);
        //     }
        // }

        $query->where(function($q) use ($request) {
            $q->whereNotNull('stock_opname.product_id')
                ->whereNotNull('stock_opname.stock_sistem')
                ->whereNotNull('stock_opname.stock_fisik');
        });

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('stock_opname.created_at', [$request->start_date, $request->end_date]);
        }
        $query->orderByDesc('stock_opname.created_at');
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
