<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Laravel\Scout\Searchable;

class Supplier extends Model
{
    use HasFactory, Searchable;

    protected $guarded = ['id'];
    
    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public function toSearchableArray()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'description' => $this->description,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    public function searchableAs()
    {
        return 'suppliers_index';
    }

    public function makeAllSearchableUsing($query)
    {
        return $query->with([]);
    }

    protected function makeSearchableUsing($query)
    {
        return $query->with([]);
    }

    public function getSearchableAttributes(): array
    {
        return ['name', 'email', 'phone', 'address', 'description'];
    }

    public function getFilterableAttributes(): array
    {
        return ['name', 'email', 'phone'];
    }

    public function getSortableAttributes(): array
    {
        return ['name', 'email', 'phone', 'created_at', 'updated_at'];
    }

    /**
     * Get the debt record associated with the supplier.
     */

    public function scopeWithDebtInfo($query)
    {
        return $query
            ->leftJoin('supplier_debts', 'supplier_debts.supplier_id', '=', 'suppliers.id')
            ->leftJoin('latest_debt_per_supplier as lds', 'supplier_debts.id', '=', 'lds.supplier_debt_id')
            ->addSelect([
                'suppliers.*',
                'supplier_debts.initial_amount as saldo_awal',
                DB::raw('COALESCE(lds.balance_after, supplier_debts.initial_amount, 0) as total_hutang')
            ]);
    }
    public function debt()
    {
        return $this->hasOne(SupplierDebt::class);
    }

}
