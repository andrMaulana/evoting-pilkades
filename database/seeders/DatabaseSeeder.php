<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Jalankan kedua seeder ini secara otomatis
        $this->call([
            AdminSeeder::class,
            KandidatSeeder::class,
        ]);
    }
}