<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductPriceHistory extends Model
{
    use HasFactory, LogsActivity;
    protected $guarded = ['id'];
    protected $table = 'product_price_histories';

    public function product()
    {
       return $this->belongsTo(Product::class);
    }

    public function purchases()
    {
       return $this->hasOne(Purchase::class, 'source_id', 'id');
    }
}
