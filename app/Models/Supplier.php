<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Supplier extends Model
{
    use Searchable;

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
}