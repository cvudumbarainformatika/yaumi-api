<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PengeluaranGudangItem extends Model
{
    protected $table = 'pengeluaran_gudang_items';

    protected $guarded = ['id'];

    public function pengeluaran_gudang()
    {
        return $this->belongsTo(PengeluaranGudang::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
