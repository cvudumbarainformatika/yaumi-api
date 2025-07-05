<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Customer extends Model
{
    use HasFactory, LogsActivity;

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



    public function scopeWithReceivableInfo($query)
    {
        return $query
            ->leftJoin('latest_receivable_per_customer as lrc', 'customers.id', '=', 'lrc.customer_id')
            ->leftJoin('customer_receivables as cr', 'customers.id', '=', 'cr.customer_id')
            ->addSelect([
                'customers.*','cr.initial_amount as saldo_awal',
                DB::raw('COALESCE(lrc.balance_after, cr.initial_amount, 0) as total_piutang')
            ]);

    }


    public function receivable(): HasOne
    {
        return $this->hasOne(CustomerReceivable::class, 'customer_id', 'id');
    }

    public function historyPiutangs(): HasMany
    {
        return $this->hasMany(CustomerReceivableHistory::class);
    }
}