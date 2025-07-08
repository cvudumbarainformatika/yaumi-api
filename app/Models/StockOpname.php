<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class StockOpname extends Model
{

    use LogsActivity;

    protected $table = 'stock_opname';
    protected $guarded = ['id'];
}
