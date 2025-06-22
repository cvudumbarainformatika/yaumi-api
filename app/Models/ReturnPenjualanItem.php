<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class ReturnPenjualanItem extends Model
{
    use LogsActivity;

    protected $fillable = ['return_penjualan_id', 'product_id', 'qty', 'harga', 'subtotal','status'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function return()
    {
        return $this->belongsTo(ReturnPenjualan::class, 'return_penjualan_id');
    }
}
