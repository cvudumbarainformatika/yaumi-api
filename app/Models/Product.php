<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'stok',
        // ... kolom lainnya
    ];
    
    /**
     * Update stok produk dengan optimistic locking
     */
    public function updateStock($newStock)
    {
        return DB::transaction(function () use ($newStock) {
            // Ambil produk terbaru
            $freshProduct = self::lockForUpdate()->find($this->id);
            
            // Jika timestamp berbeda, berarti ada perubahan bersamaan
            if ($freshProduct->updated_at->ne($this->updated_at)) {
                throw new \Exception("Produk telah diubah oleh proses lain. Silakan coba lagi.");
            }
            
            // Update stok
            $freshProduct->stok = $newStock;
            $freshProduct->save();
            
            // Refresh model saat ini
            $this->refresh();
            
            return $this;
        });
    }
}
