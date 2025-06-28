<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class CashFlow extends Model
{
    use LogsActivity;


    protected $table = 'cash_flows';

    protected $fillable = [
        'tanggal',
        'tipe',
        'kas_id',
        'kasir_id',
        'jumlah',
        'kategori',
        'keterangan',
        'source_type',
        'source_id',
        'user_id',
    ];

    public function kas()
    {
        return $this->belongsTo(Kas::class);
    }

    public function kasir()
    {
        return $this->belongsTo(User::class, 'kasir_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
