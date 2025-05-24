<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sales;
use App\Models\SalesItem;
use App\Models\Product;
use App\Models\ProductStockMutation;
use App\Models\CustomerReceivable;
use App\Models\CustomerReceivableHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'paid' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
            'payment_method' => 'nullable|string',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'reference' => 'nullable|string',
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
            $uniqueCode = 'PJ-' . date('Ymd') . '-' . uniqid();
            $sales = Sales::create([
                'customer_id' => $validated['customer_id'],
                'total' => $grandTotal,
                'paid' => $validated['paid'],
                'status' => 'completed',
                'notes' => $validated['notes'] ?? null,
                'unique_code' => $uniqueCode,
                'payment_method' => $validated['payment_method'] ?? null,
                'discount' => $discount,
                'tax' => $tax,
                'reference' => $validated['reference'] ?? null,
                'cashier_id' => $validated['cashier_id'] ?? null,
                'received' => ($validated['paid'] >= $grandTotal),
                'total_received' => $validated['paid'],
            ]);

            foreach ($validated['items'] as $item) {
                SalesItem::create([
                    'sales_id' => $sales->id,
                    'product_id' => $item['product_id'],
                    'qty' => $item['qty'],
                    'price' => $item['price'],
                    'subtotal' => $item['qty'] * $item['price'],
                ]);
                // Mutasi stok keluar dengan running balance
                $lastMutation = ProductStockMutation::getLastMutation($item['product_id']);
                $stockBefore = $lastMutation ? $lastMutation->stock_after : 0;

                // Validasi stok cukup
                if ($stockBefore < $item['qty']) {
                    throw new \Exception("Stok tidak cukup untuk produk ID: {$item['product_id']}");
                }

                $stockAfter = $stockBefore - $item['qty'];

                ProductStockMutation::createMutation([
                    'product_id' => $item['product_id'],
                    'mutation_type' => 'out',
                    'qty' => $item['qty'],
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'source_type' => 'sales',
                    'source_id' => $sales->id,
                    'notes' => 'Penjualan',
                ]);

                // Update stok produk
                $product = Product::find($item['product_id']);
                $product->update([
                    'stock' => ProductStockMutation::getLastMutation($item['product_id'])->stock_after
                ]);
            }

            // Piutang customer
            $receivable = CustomerReceivable::firstOrCreate(
                ['customer_id' => $validated['customer_id']],
                ['initial_amount' => 0, 'current_amount' => 0]
            );
            $piutang = $total - $validated['paid'];
            if ($piutang > 0) {
                $receivable->increment('current_amount', $piutang);
            }
            CustomerReceivableHistory::create([
                'customer_id' => $validated['customer_id'],
                'sales_id' => $sales->id,
                'type' => 'sales',
                'amount' => $piutang,
                'notes' => 'Piutang dari penjualan',
            ]);

            return response()->json(['sales' => $sales->load('items')], 201);
        });
    }

    // Tambahkan method lain seperti show, index, cancel, dsb sesuai kebutuhan
}
