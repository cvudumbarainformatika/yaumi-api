<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('id_ID');
        
        $categories = [
            'Makanan',
            'Minuman',
            'Snack',
            'Alat Tulis',
            'Pembersih',
            'Kosmetik',
            'Obat-obatan',
            'Perlengkapan Rumah',
        ];

        foreach ($categories as $category) {
            Category::create([
                'name' => $category,
                'description' => $faker->sentence()
            ]);
        }
    }
}