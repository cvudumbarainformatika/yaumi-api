<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use HasFactory, LogsActivity;
    protected $fillable = [
        'supplier_id',
        'purchase_order_id',
        'date',
        'due_date',
        'total',
        'paid',
        'debt',
        'note',
        'unique_code',
        'payment_method',
        'invoice_number',
        'skip_stock_mutation',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function phd() // Pembayaran Hutang Detail
    {
        return $this->hasMany(PembayaranHutangDetail::class);
    }
}
