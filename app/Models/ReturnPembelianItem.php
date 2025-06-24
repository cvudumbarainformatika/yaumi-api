<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class ReturnPembelianItem extends Model
{
    use LogsActivity;

    protected $fillable = [
        'return_pembelian_id',
        'product_id',
        'qty',
        'harga',
        'subtotal',
        'alasan'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function return()
    {
        return $this->belongsTo(ReturnPembelian::class, 'return_pembelian_id');
    }
}
