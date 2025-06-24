<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class ReturnPembelian extends Model
{
    use LogsActivity;

    protected $fillable = ['unique_code', 'nota', 'supplier_id','user_id', 'tanggal', 'keterangan', 'total'];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function pembelian()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function items()
    {
        return $this->hasMany(ReturnPembelianItem::class);
    }
}
