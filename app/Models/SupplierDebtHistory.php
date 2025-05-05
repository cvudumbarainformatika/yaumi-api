<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SupplierDebtHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_debt_id',
        'mutation_type',
        'amount',
        'balance_before',
        'balance_after',
        'source_type',
        'source_id',
        'notes',
    ];

    public function supplierDebt()
    {
        return $this->belongsTo(SupplierDebt::class);
    }

    /**
     * Mendapatkan histori hutang terakhir untuk supplier debt tertentu
     */
    public static function getLastHistory($supplierDebtId)
    {
        return self::where('supplier_debt_id', $supplierDebtId)
            ->orderBy('id', 'desc')
            ->first();
    }

    /**
     * Membuat histori hutang baru dengan running balance
     */
    public static function createHistory($data)
    {
        return DB::transaction(function () use ($data) {
            // Lock baris supplier debt untuk mencegah konkurensi
            $supplierDebt = SupplierDebt::lockForUpdate()->findOrFail($data['supplier_debt_id']);
            
            // Ambil histori terakhir dengan locking
            $lastHistory = self::where('supplier_debt_id', $data['supplier_debt_id'])
                ->orderBy('id', 'desc')
                ->lockForUpdate()
                ->first();
            
            $balanceBefore = $lastHistory ? $lastHistory->balance_after : $supplierDebt->initial_amount;
            
            // Hitung saldo setelah mutasi
            $balanceAfter = $balanceBefore;
            if ($data['mutation_type'] === 'increase') {
                $balanceAfter += $data['amount'];
            } else {
                $balanceAfter -= $data['amount'];
            }
            
            // Buat histori baru dengan saldo awal dan akhir
            $history = self::create([
                'supplier_debt_id' => $data['supplier_debt_id'],
                'mutation_type' => $data['mutation_type'],
                'amount' => $data['amount'],
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'source_type' => $data['source_type'],
                'source_id' => $data['source_id'],
                'notes' => $data['notes'],
            ]);
            
            // Update saldo hutang supplier
            $supplierDebt->update(['current_amount' => $balanceAfter]);
            
            return $history;
        }, 5); // Retry 5 kali jika terjadi deadlock
    }
}
