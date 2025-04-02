<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'phone',
        'email',
        'description'
    ];

    /**
     * Get the receivable record associated with the customer.
     */
    public function receivable(): HasOne
    {
        return $this->hasOne(CustomerReceivable::class);
    }
}