<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class PembayaranHutang extends Model
{
    use LogsActivity;

    protected $fillable = [
        'supplier_id', 'user_id', 'tanggal', 'total',
        'metode_pembayaran', 'keterangan',
        'rekening_tujuan', 'bank_tujuan', 'nama_rekening',
        'nomor_giro', 'tanggal_jatuh_tempo'
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function details()
    {
        return $this->hasOne(PembayaranHutangDetail::class); // ini aslinya has one
    }

}
