<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class PembayaranHutangDetail extends Model
{

    use LogsActivity;

    protected $fillable = [
        'pembayaran_hutang_id', 'purchase_id', 'dibayar'
    ];

    public function pembayaran()
    {
        return $this->belongsTo(PembayaranHutang::class);
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }
}
