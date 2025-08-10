<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class PenerimaanGudang extends Model
{
    use LogsActivity;

    protected $table = 'penerimaan_gudangs';
    protected $guarded = ['id'];

    public function items()
    {
        return $this->hasMany(PenerimaanGudangItem::class);
    }
}
