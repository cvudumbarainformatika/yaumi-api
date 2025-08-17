<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class StockOpnameGudang extends Model
{

    use LogsActivity;

    protected $table = 'stock_opname_gudangs';
    protected $guarded = ['id'];
}
