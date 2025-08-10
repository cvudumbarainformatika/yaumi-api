<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PenerimaanGudangItem extends Model
{
    protected $table = 'penerimaan_gudang_items';

    protected $guarded = ['id'];

    public function penerimaan_gudang()
    {
        return $this->belongsTo(PenerimaanGudang::class);
    }
}
