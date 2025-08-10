<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'unique_code',
        'order_date',
        'notes',
        'status',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * Get the purchases associated with this purchase order.
     */
    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }

    /**
     * Get all purchase items associated with this purchase order through purchases.
     */
    public function allPurchaseItems()
    {
        return $this->hasManyThrough(PurchaseItem::class, Purchase::class);
    }

    /**
     * Update the status of this purchase order based on its items.
     */
    public function updateStatus()
    {
        $items = $this->items;
        
        if ($items->isEmpty()) {
            return;
        }
        
        $allReceived = $items->every(function ($item) {
            return $item->is_fully_received;
        });
        
        $anyReceived = $items->contains(function ($item) {
            return $item->received_quantity > 0;
        });
        
        if ($allReceived) {
            $this->status = 'received';
        } elseif ($anyReceived) {
            $this->status = 'partial'; // ini aslinya partial
        }
        
        $this->save();
    }
}
