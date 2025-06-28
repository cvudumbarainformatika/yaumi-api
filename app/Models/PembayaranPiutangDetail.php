<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PembayaranPiutangDetail extends Model
{
    protected $fillable = ['pembayaran_piutang_id', 'sale_id', 'dibayar'];

    public function pembayaran()
    {
        return $this->belongsTo(PembayaranPiutang::class, 'pembayaran_piutang_id');
    }

    public function sale()
    {
        return $this->belongsTo(Sales::class);
    }
}
