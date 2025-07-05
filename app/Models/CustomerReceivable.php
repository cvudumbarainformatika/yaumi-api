<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerReceivable extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'customer_id',
        'initial_amount',
        'current_amount',
        'notes',
    ];

    /**
     * Get the customer that owns the receivable.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
    
}