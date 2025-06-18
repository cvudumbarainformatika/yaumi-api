<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sales;
use App\Models\SalesItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesController extends Controller
{

    public function index(Request $request)
    {
        $query = Sales::query()
            ->select('sales.*', 'customers.name as customer_name')
            ->leftJoin('customers', 'sales.customer_id', '=', 'customers.id')
            ->with(['customer', 'items.product']);

            // Filter pencarian jika parameter q tidak kosong
        if ($request->filled('q') && !empty($request->q)) {
            $search = $request->q;
            $query->where(function($q) use ($search) {
                $q->where('sales.unique_code', 'like', "%{$search}%")
                  ->orWhereExists(function ($query) use ($search) {
                      $query->select(DB::raw(1))
                            ->from('customers')
                            ->whereColumn('customers.id', 'sales.customer_id')
                            ->where('customers.name', 'like', "%{$search}%");
                  });
            });
        }

        // Filter berdasarkan customer_id
        if ($request->filled('status') && !empty($request->status)) {
            if ($request->status !== 'semua') {
                $query->where('sales.payment_method', '=', $request->status);
            }
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('sales.created_at', [$request->start_date, $request->end_date]);
        }

        // $purchases = $query->orderByDesc('purchases.id')->simplePaginate(15);
        $perPage = $request->input('per_page', 10);
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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'unique_code' => 'required|string|unique:sales', // Tambahkan validasi untuk 'unique_code'
            'reference' => 'required|string|unique:sales', // Tambahkan validasi untuk 'unique_code'
            'customer_id' => 'nullable|exists:customers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
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
            'cashier_id' => 'nullable|exists:users,id',
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
                'cashier_id' => $validated['cashier_id'] ?? null,
                'received' => ($validated['paid'] >= $grandTotal),
                'total_received' => $grandTotal,
            ]);

            foreach ($validated['items'] as $item) {
                SalesItem::create([
                    'sales_id' => $sales->id,
                    'product_id' => $item['product_id'],
                    'qty' => $item['qty'],
                    'price' => $item['price'],
                    'subtotal' => $item['qty'] * $item['price'],
                ]);
                // // Mutasi stok keluar dengan running balance
                // $lastMutation = ProductStockMutation::getLastMutation($item['product_id']);
                // $stockBefore = $lastMutation ? $lastMutation->stock_after : 0;

                // Validasi stok cukup
                // if ($stockBefore < $item['qty']) {
                //     throw new \Exception("Stok tidak cukup untuk produk ID: {$item['product_id']}");
                // }

                // $stockAfter = $stockBefore - $item['qty'];

                // ProductStockMutation::createMutation([
                //     'product_id' => $item['product_id'],
                //     'mutation_type' => 'out',
                //     'qty' => $item['qty'],
                //     'stock_before' => $stockBefore,
                //     'stock_after' => $stockAfter,
                //     'source_type' => 'sales',
                //     'source_id' => $sales->id,
                //     'notes' => 'Penjualan',
                // ]);

                // Update stok produk
                // $product = Product::find($item['product_id']);
                // $product->update([
                //     'stock' => ProductStockMutation::getLastMutation($item['product_id'])->stock_after
                // ]);
            }

            // Piutang customer
            // $piutang = $grandTotal - $validated['paid'];
            // if ($piutang > 0 && $validated['payment_method' === 'kredit']) {
            //     $receivable = CustomerReceivable::firstOrCreate(
            //         ['customer_id' => $validated['customer_id']],
            //         ['initial_amount' => 0, 'current_amount' => 0]
            //     );
            //         $receivable->increment('current_amount', $piutang);
            //     CustomerReceivableHistory::create([
            //         'customer_id' => $validated['customer_id'],
            //         'sales_id' => $sales->id,
            //         'type' => 'sales',
            //         'amount' => $piutang,
            //         'notes' => 'Piutang dari penjualan',
            //     ]);
            // }

            return response()->json(['sales' => $sales->load('items')], 201);
        });
    }

    // Tambahkan method lain seperti show, index, cancel, dsb sesuai kebutuhan
}
