<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sales extends Model
{
    use HasFactory, LogsActivity;
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

    public function return()
    {
       return $this->hasOne(ReturnPenjualan::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesItem::class);
    }
    public function ppd(): HasMany
    {
        return $this->hasMany(PembayaranPiutangDetail::class, 'sale_id');
    }
    public function cashier()
    {
       return $this->belongsTo(User::class, 'cashier_id');
    }
}
