<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Bikin satu akun utama untuk panitia
        User::create([
            'name' => 'Panitia Pilkades Curug',
            'email' => 'admin@curug.desa.id', // Ini yang dipakai buat login
            'password' => Hash::make('panitia2026') // Passwordnya: panitia2026
        ]);
    }
}