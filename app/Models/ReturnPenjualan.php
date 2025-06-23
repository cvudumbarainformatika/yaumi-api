<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class ReturnPenjualan extends Model
{
    use LogsActivity;

    protected $fillable = [
        'unique_code',
        'nota',
        'user_id',
        'sales_id',
        'customer_id',
        'tanggal',
        'keterangan',
        'total',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
       return $this->belongsTo(User::class);
    }
    public function penjualan()
    {
        return $this->belongsTo(Sales::class);
    }

    public function items()
    {
        return $this->hasMany(ReturnPenjualanItem::class);
    }
}
