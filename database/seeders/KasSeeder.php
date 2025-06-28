<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Kas;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class KasSeeder extends Seeder
{
    public function run(): void
    {
        Kas::insert([
            [
                'nama' => 'Kasir',
                'tipe' => 'kasir',
                'bank_name' => null,
                'saldo_awal' => 0,
                'no_rekening' => '1234567890',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'Bank BCA',
                'tipe' => 'bank',
                'bank_name' => 'BCA',
                'no_rekening' => '1234567890',
                'saldo_awal' => 0,
                 'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nama' => 'Bank BRI',
                'tipe' => 'bank',
                'bank_name' => 'BRI',
                'no_rekening' => '1234567890',
                'saldo_awal' => 0,
                 'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }
}