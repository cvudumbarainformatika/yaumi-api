<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Laravel\Scout\Searchable;

class Product extends Model
{
    use Searchable, LogsActivity;

    // protected $fillable = [
    //     'name',
    //     'description',
    //     'price',
    //     'stock',
    //     'barcode',
    //     'category_id',
    //     'satuan_id',
    //     'hargabeli',
    //     'hargajual',
    //     'hargajualcust',
    //     'hargajualantar',
    //     'minstock',
    //     'rak',
    // ];


    /**
     * Update stok produk dengan optimistic locking
     */
    // public function updateStock($newStock)
    // {
    //     return DB::transaction(function () use ($newStock) {
    //         // Ambil produk terbaru
    //         $freshProduct = self::lockForUpdate()->find($this->id);

    //         // Jika timestamp berbeda, berarti ada perubahan bersamaan
    //         if ($freshProduct->updated_at->ne($this->updated_at)) {
    //             throw new \Exception("Produk telah diubah oleh proses lain. Silakan coba lagi.");
    //         }

    //         // Update stok
    //         $freshProduct->stock = $newStock;
    //         $freshProduct->save();

    //         // Refresh model saat ini
    //         $this->refresh();

    //         return $this;
    //     });
    // }

    protected $guarded = ['id'];


    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'barcode' => $this->barcode,
            'name' => $this->name,
            'rak' => $this->rak,
            'category_id' => $this->category_id,
            'category_name' => $this->category->name,
            'satuan_id' => $this->satuan_id,
            'satuan_name' => $this->satuan->name,
            'hargabeli' => $this->hargabeli,
            'hargajual' => $this->hargajual,
            'hargajualcust' => $this->hargajualcust,
            'hargajualantar' => $this->hargajualantar,
            'stock' => $this->stock,
            'minstock' => $this->minstock,
            'is_low_stock' => $this->stock > 0 && $this->stock <= $this->minstock,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }


    public function scopeWithStockInfo($query)
    {
        return $query
            ->leftJoin('latest_stock_per_product as lsp', 'products.id', '=', 'lsp.product_id')
            ->leftJoin('satuans', 'products.satuan_id', '=', 'satuans.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->addSelect([
                'products.*',
                'categories.name as category_name',
                'satuans.name as satuan_name',
                DB::raw('COALESCE(lsp.stock, products.stock) AS stock_akhir'),
                'lsp.tanggal'
            ]);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function satuan()
    {
        return $this->belongsTo(Satuan::class);
    }

    public function searchableAs(): string
    {
        return 'products';
    }


    public function purchaseOrderItems()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function stockMutations()
    {
        return $this->hasMany(ProductStockMutation::class);
    }


}
