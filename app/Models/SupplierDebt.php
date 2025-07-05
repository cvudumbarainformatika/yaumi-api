<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierDebt extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'supplier_id',
        'initial_amount',
        'current_amount',
        'notes',
    ];

    /**
     * Get the supplier that owns the debt.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function histories()
    {
       return $this->hasMany(SupplierDebtHistory::class);
    }
}