<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PembayaranPiutang extends Model
{
     protected $fillable = [
        'customer_id', 'tanggal', 'total', 'metode_pembayaran', 'bank_tujuan',
        'rekening_tujuan', 'nama_rekening', 'nomor_giro', 'tanggal_jatuh_tempo',
        'keterangan', 'user_id'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function details()
    {
        return $this->hasMany(PembayaranPiutangDetail::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
