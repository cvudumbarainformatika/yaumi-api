<?php

namespace Database\Seeders;

use App\Models\Satuan;
use Illuminate\Database\Seeder;

class SatuanSeeder extends Seeder
{
    public function run(): void
    {
        $satuans = ['PCS', 'BOX', 'PACK', 'DUS', 'LUSIN', 'ROLL', 'SET', 'UNIT', 'KG', 'GRAM'];
        
        foreach ($satuans as $satuan) {
            Satuan::create([
                'name' => $satuan,
                'description' => 'Satuan ' . strtolower($satuan)
            ]);
        }
    }
}
