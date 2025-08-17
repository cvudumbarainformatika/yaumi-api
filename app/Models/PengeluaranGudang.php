<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class PengeluaranGudang extends Model
{
    use LogsActivity;

    protected $table = 'pengeluaran_gudangs';

    protected $guarded = ['id'];

    public function items()
    {
        return $this->hasMany(PengeluaranGudangItem::class);
    }
}
