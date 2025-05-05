<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'quantity',
        'received_quantity',
        'price',
        'total',
        'notes',
        'status',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the purchase items associated with this purchase order item.
     */
    public function purchaseItems()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /**
     * Get the total quantity received for this purchase order item.
     */
    public function getReceivedQuantityAttribute()
    {
        return $this->purchaseItems()->sum('qty');
    }

    /**
     * Get the remaining quantity to be received.
     */
    public function getRemainingQuantityAttribute()
    {
        return $this->quantity - $this->received_quantity;
    }

    /**
     * Check if this item is fully received.
     */
    public function getIsFullyReceivedAttribute()
    {
        return $this->remaining_quantity <= 0;
    }
}
