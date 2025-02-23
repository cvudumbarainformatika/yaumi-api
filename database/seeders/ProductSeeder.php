<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('id_ID');
        
        for ($i = 0; $i < 100; $i++) {
            Product::create([
                'barcode' => $faker->unique()->ean13(),
                'category_id' => rand(1, 5),
                'name' => $faker->words(3, true),
                'satuan_id' => rand(1, 10), // Match with number of satuans
                'hargabeli' => $faker->numberBetween(1000, 100000),
                'hargajual' => $faker->numberBetween(2000, 150000),
                'hargajualcust' => $faker->numberBetween(1500, 120000),
                'hargajualantar' => $faker->numberBetween(1800, 130000),
                'stock' => $faker->numberBetween(0, 1000),
                'minstock' => $faker->numberBetween(5, 100),
                'rak' => $faker->randomElement(['A', 'B', 'C', 'D']) . $faker->numberBetween(1, 9)
            ]);
        }
    }
}
