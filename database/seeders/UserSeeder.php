<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'harry@tokoyaumi47.my.id'], // cari by email, biar gak double
            [
                'name' => 'Hari Yadi',
                'username' => 'harry',
                'password' => Hash::make('harry141312'), // ganti sesuai kebutuhan
                'role' => 'root', // pastikan kolom ini ada di tabel users
            ]
        );
    }
}
