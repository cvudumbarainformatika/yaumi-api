<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Scout\Searchable;

class Product extends Model
{
    use Searchable;

    protected $guarded = ['id'];

    protected $casts = [
        'hargabeli' => 'decimal:2',
        'hargajual' => 'decimal:2',
        'hargajualcust' => 'decimal:2',
        'hargajualantar' => 'decimal:2',
        'stock' => 'integer',
        'minstock' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function satuan(): BelongsTo
    {
        return $this->belongsTo(Satuan::class);
    }

    public function searchableAs(): string
    {
        return 'products';
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'barcode' => $this->barcode,
            'name' => $this->name,
            'rak' => $this->rak,
            'category_id' => $this->category_id,
            'category_name' => $this->category->name,
            'satuan_id' => $this->satuan_id,
            'satuan_name' => $this->satuan->name,
            'hargabeli' => $this->hargabeli,
            'hargajual' => $this->hargajual,
            'hargajualcust' => $this->hargajualcust,
            'hargajualantar' => $this->hargajualantar,
            'stock' => $this->stock,
            'minstock' => $this->minstock,
            'is_low_stock' => $this->stock > 0 && $this->stock <= $this->minstock,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    public function purchaseOrderItems()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }
}
