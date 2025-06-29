<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerReceivableHistory extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'customer_id',
        'sales_id',
        'type',
        'amount',
        'notes',
    ];

    // Define relationships if necessary
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function sales()
    {
        return $this->belongsTo(Sales::class);
    }
}
