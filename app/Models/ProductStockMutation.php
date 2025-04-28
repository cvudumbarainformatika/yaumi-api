<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductStockMutation extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'mutation_type',
        'qty',
        'source_type',
        'source_id',
        'notes',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
