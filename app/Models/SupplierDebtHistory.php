<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierDebtHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_debt_id',
        'mutation_type',
        'amount',
        'source_type',
        'source_id',
        'notes',
    ];

    public function supplierDebt()
    {
        return $this->belongsTo(SupplierDebt::class);
    }
}
