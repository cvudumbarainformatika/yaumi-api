<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Kas extends Model
{
    use LogsActivity;
    protected $table = 'kas';

    protected $fillable = [
        'nama',
        'tipe',
        'bank_name',
        'no_rekening',
        'saldo_awal',
    ];

    public function cashFlows()
    {
        return $this->hasMany(CashFlow::class);
    }
}
