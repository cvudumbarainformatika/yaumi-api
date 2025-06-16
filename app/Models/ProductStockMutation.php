<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ProductStockMutation extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'mutation_type',
        'qty',
        'stock_before',
        'stock_after',
        'source_type',
        'source_id',
        'notes',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Mendapatkan mutasi stok terakhir untuk produk tertentu
     */
    public static function getLastMutation($productId)
    {
        return self::where('product_id', $productId)
            ->orderBy('id', 'desc')
            ->first();
    }

    /**
     * Membuat mutasi stok baru dengan running balance dan penanganan konkurensi
     */
    public static function createMutation($data)
    {
        // Gunakan transaction dengan locking untuk mencegah race condition
        return DB::transaction(function () use ($data) {
            // Lock baris produk untuk mencegah konkurensi
            $product = Product::lockForUpdate()->findOrFail($data['product_id']);

            // Ambil mutasi terakhir dengan locking
            $lastMutation = self::where('product_id', $data['product_id'])
                ->orderBy('id', 'desc')
                ->lockForUpdate()
                ->first();

            $stockBefore = $lastMutation ? $lastMutation->stock_after : 0;

            // Hitung stok setelah mutasi
            $stockAfter = $stockBefore;
            if ($data['mutation_type'] === 'in') {
                $stockAfter += $data['qty'];
            } else {
                // Validasi stok cukup untuk pengurangan
                // if ($stockAfter < $data['qty']) {
                //     throw new \Exception("Stok tidak cukup untuk produk ID: {$data['product_id']}");
                // }
                $stockAfter -= $data['qty'];
            }

            // Buat mutasi baru dengan stok awal dan akhir
            $mutation = self::create([
                'product_id' => $data['product_id'],
                'mutation_type' => $data['mutation_type'],
                'qty' => $data['qty'],
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'source_type' => $data['source_type'],
                'source_id' => $data['source_id'],
                'notes' => $data['notes'],
            ]);

            // Update stok produk
            $product->update(['stock' => $stockAfter]); // Ubah dari 'stok' menjadi 'stock'

            return $mutation;
        }, 5); // Retry 5 kali jika terjadi deadlock
    }
}
