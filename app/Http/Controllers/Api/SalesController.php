<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sales;
use App\Models\SalesItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SalesController extends Controller
{

    public function index(Request $request)
    {
        $query = Sales::query()
            ->select('sales.*', 'customers.name as customer_name', 'users.name as cashier_name')
            ->leftJoin('customers', 'sales.customer_id', '=', 'customers.id')
            ->leftJoin('users', 'sales.cashier_id', '=', 'users.id')
            ->with(['customer', 'items.product']);

            // Filter pencarian jika parameter q tidak kosong
        if ($request->filled('q') && !empty($request->q)) {
            $search = $request->q;
            $query->where(function($q) use ($search) {
                $q->where('sales.unique_code', 'like', "%{$search}%")
                  ->orWhere('customers.name', 'like', "%{$search}%")
                  ->orWhere('users.name', 'like', "%{$search}%");
            });
        }

        // Filter berdasarkan customer_id
        if ($request->filled('status') && !empty($request->status)) {
            if ($request->status !== 'semua') {
                $query->where('sales.payment_method', '=', $request->status);
            }
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->when($request->filled('start_date') && $request->filled('end_date'), function ($q) use ($request) {
                $start = $request->start_date . ' 00:00:00';
                $end = $request->end_date . ' 23:59:59';
                $q->whereBetween('sales.created_at', [$start, $end]);
            });
        }

        // $purchases = $query->orderByDesc('purchases.id')->simplePaginate(15);
        $perPage = $request->input('per_page', 10);

        $query->orderBy($request->input('sort_by', 'created_at'), $request->input('sort_direction', 'desc'));

        $totalCount = (clone $query)->count();

        // Lakukan pagination dengan simplePaginate
        $sales = $query->simplePaginate($perPage);

        $data = [
            'data' => $sales->items(),
            'meta' => [
                'first' => $sales->url(1),
                'last' => null, // SimplePaginator tidak menyediakan ini
                'prev' => $sales->previousPageUrl(),
                'next' => $sales->nextPageUrl(),
                'current_page' => $sales->currentPage(),
                'per_page' => (int)$perPage,
                'total' => (int)$totalCount,
                'last_page' => ceil($totalCount / $perPage),
                'from' => (($sales->currentPage() - 1) * $perPage) + 1,
                'to' => min($sales->currentPage() * $perPage, $totalCount),
            ],
        ];

        return response()->json($data);

    }

    public function search(Request $request)
    {
        $search = $request->input('q');
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);
        $offset = ($page - 1) * $perPage;

        // Base query reuseable
        $baseQuery = function ($q) use ($search) {
            $q->leftJoin('customers', 'sales.customer_id', '=', 'customers.id')
            ->leftJoin('sales_items', 'sales.id', '=', 'sales_items.sales_id')
            ->leftJoin('products', 'sales_items.product_id', '=', 'products.id')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('sales.unique_code', 'like', "%{$search}%")
                        ->orWhere('customers.name', 'like', "%{$search}%")
                        ->orWhere('products.name', 'like', "%{$search}%");
                });
            });
        };

        // Count total unique sales.id
        $totalCount = DB::table('sales')
            ->when(true, $baseQuery)
            ->distinct('sales.id')
            ->count('sales.id');

        // Get paginated data
        $query = Sales::query()
            ->select(
                'sales.*',
                'customers.name as customer_name'
            )
            ->when(true, $baseQuery)
            ->with(['customer', 'items.product', 'return.items.product'])
            ->groupBy('sales.id', 'sales.unique_code', 'sales.created_at', 'sales.status', 'customers.name')
            ->orderByDesc('sales.created_at')
            ->offset($offset)
            ->limit($perPage);

        $sales = $query->get();

        return response()->json([
            'data' => $sales,
            'meta' => [
                'current_page' => (int) $page,
                'per_page' => (int) $perPage,
                'total' => (int) $totalCount,
                'last_page' => ceil($totalCount / $perPage),
                'from' => $offset + 1,
                'to' => $offset + $sales->count(),
                'prev' => $page > 1 ? $page - 1 : null,
                'next' => ($offset + $sales->count()) < $totalCount ? $page + 1 : null,
            ]
        ]);
    }

    public function store(Request $request)
    {

        // return $request->all();

        $validated = $request->validate([
            'unique_code' => 'required|string|unique:sales', // Tambahkan validasi untuk 'unique_code'
            'reference' => 'required|string|unique:sales', // Tambahkan validasi untuk 'unique_code'
            'customer_id' => 'nullable|exists:customers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.harga_modal' => 'required|integer|min:0',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'paid' => 'required|numeric|min:0',
            'bayar' => 'required|numeric|min:0',
            'kembali' => 'nullable|numeric|min:0',
            'dp' => 'nullable|numeric|min:0',
            'tempo' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'payment_method' => 'nullable|string',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
        ]);

        return DB::transaction(function () use ($validated) {
            $total = 0;
            foreach ($validated['items'] as $item) {
                $total += $item['qty'] * $item['price'];
            }
            $discount = $validated['discount'] ?? 0;
            $tax = $validated['tax'] ?? 0;
            $grandTotal = $total - $discount + $tax;
            $uniqueCode = $validated['unique_code'] ?? null;
            $bayar = $validated['bayar'];
            $kembali = $validated['kembali']?? 0;
            $cashierId = Auth::id() ?? null;
            $sales = Sales::create([
                'customer_id' => $validated['customer_id'],
                'total' => $grandTotal,
                'paid' => $validated['paid'],
                'bayar' => $bayar,
                'kembali' => $kembali,
                'dp' => $validated['dp'] ?? 0,
                'tempo' => $validated['tempo'] ?? 0,
                'status' => 'completed',
                'notes' => $validated['notes'] ?? null,
                'payment_method' => $validated['payment_method'] ?? null,
                'discount' => $discount,
                'tax' => $tax,
                'reference' => $uniqueCode,
                'unique_code' => $uniqueCode,
                'cashier_id' => $cashierId,
                'received' => ($validated['paid'] >= $grandTotal),
                'total_received' => $grandTotal,
            ]);

            foreach ($validated['items'] as $item) {
                SalesItem::create([
                    'sales_id' => $sales->id,
                    'product_id' => $item['product_id'],
                    'harga_modal' => $item['harga_modal'],
                    'qty' => $item['qty'],
                    'price' => $item['price'],
                    'subtotal' => $item['qty'] * $item['price'],
                ]);
            }


            return response()->json(['sales' => $sales->load(['items.product', 'customer', 'cashier'])], 201);
        });
    }

    // Tambahkan method lain seperti show, index, cancel, dsb sesuai kebutuhan
    public function report(Request $request)
    {
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 15);
        $offset = ($page - 1) * $perPage;

        $query = Sales::query()
            ->select(
                'sales.id',
                'sales.unique_code',
                'sales.total',
                'sales.discount',
                'sales.tax',
                'sales.paid',
                'sales.bayar',
                'sales.kembali',
                'sales.dp',
                'sales.payment_method',
                'sales.status',
                'sales.created_at',
                'customers.name as customer_name',
                'cashiers.name as cashier_name'
            )
            ->leftJoin('customers', 'sales.customer_id', '=', 'customers.id')
            ->leftJoin('users as cashiers', 'sales.cashier_id', '=', 'cashiers.id')
            ->when($request->filled('q'), function ($q) use ($request) {
                $search = $request->q;
                $q->where(function ($sub) use ($search) {
                    $sub->where('sales.unique_code', 'like', "%{$search}%")
                        ->orWhere('customers.name', 'like', "%{$search}%")
                        ->orWhere('cashiers.name', 'like', "%{$search}%");
                });
            })
             ->when($request->filled('status'), function ($q) use ($request) {
                $search = $request->status;
                $q->where(function ($sub) use ($search) {
                    $sub->where('sales.payment_method', '=', $search);
                });
            })
            ->when($request->filled('start_date') && $request->filled('end_date'), function ($q) use ($request) {
                $start = $request->start_date . ' 00:00:00';
                $end = $request->end_date . ' 23:59:59';
                $q->whereBetween('sales.created_at', [$start, $end]);
            })
            ->orderByDesc('sales.created_at');

        $total = (clone $query)->count();

        $sales = $query
            ->offset($offset)
            ->limit($perPage)
            ->get();

        return response()->json([
            'data' => $sales,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
                'from' => $offset + 1,
                'to' => $offset + $sales->count(),
                'prev' => $page > 1 ? $page - 1 : null,
                'next' => ($offset + $sales->count()) < $total ? $page + 1 : null,
            ]
        ]);
    }

    public function rekap(Request $request)
    {
       $query = Sales::query()
        ->leftJoin('customers', 'sales.customer_id', '=', 'customers.id')
        ->leftJoin('users as cashiers', 'sales.cashier_id', '=', 'cashiers.id')
        ->when($request->filled('q'), function ($q) use ($request) {
            $search = $request->q;
            $q->where(function ($sub) use ($search) {
                $sub->where('sales.unique_code', 'like', "%{$search}%")
                    ->orWhere('customers.name', 'like', "%{$search}%")
                    ->orWhere('cashiers.name', 'like', "%{$search}%");
            });
        })
        ->when($request->filled('status'), function ($q) use ($request) {
            $search = $request->status;
            $q->where(function ($sub) use ($search) {
                $sub->where('sales.payment_method', '=', $search);
            });
        })
    
       ->when($request->filled('start_date') && $request->filled('end_date'), function ($q) use ($request) {
                $start = $request->start_date . ' 00:00:00';
                $end = $request->end_date . ' 23:59:59';
                $q->whereBetween('sales.created_at', [$start, $end]);
            });

        $summary = $query->selectRaw('
            COUNT(sales.id) as jumlah_transaksi,
            SUM(sales.total) as total_penjualan,
            SUM(sales.discount) as total_diskon,
            SUM(sales.tax) as total_pajak,
            SUM(sales.paid) - SUM(sales.kembali) as total_dibayar,
            SUM(CASE WHEN sales.payment_method = "cash" THEN sales.total ELSE 0 END) as total_cash,
            SUM(CASE WHEN sales.payment_method = "credit" THEN sales.total ELSE 0 END) as total_credit
        ')->first();

        return response()->json($summary);
    }
    
}
