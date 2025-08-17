<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GudangStockMutation extends Model
{
    protected $table = 'gudang_stock_mutations';
    protected $guarded = ['id'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

     public static function getLastMutation($productId)
    {
        return self::where('product_id', $productId)
            ->orderBy('id', 'desc')
            ->first();
    }
}
