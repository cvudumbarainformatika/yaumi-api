<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sales extends Model
{
    use HasFactory;
    protected $table = 'sales';
    protected $guarded = ['id'];

    // protected $fillable = [
    //     'customer_id',
    //     'unique_code',
    //     'total',
    //     'paid',
    //     'bayar',
    //     'kembali',
    //     'status',
    //     'notes',
    //     'payment_method',
    //     'discount',
    //     'tax',
    //     'reference',
    //     'cashier_id',
    //     'received',
    //     'total_received',
    // ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesItem::class);
    }
}
