<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KandidatSeeder extends Seeder
{
    public function run(): void
    {
        // Data kandidat final, disalin dari menu admin (tabel kandidat) pada 21 Okt 2026.
        // Foto ada di public/img/kandidat_N.jpg, jadi tetap aman walau tabel di-reset (migrate:fresh).
        // Kalau nanti ada perubahan kandidat lewat admin, perbarui juga daftar ini supaya tidak hilang saat reset.
        $sekarang = now();

        DB::table('kandidat')->insert([
            ['id' => 1, 'no_urut' => 1, 'nama' => 'WULAN WULANDARI',     'foto' => 'img/kandidat_1.jpg', 'created_at' => $sekarang, 'updated_at' => $sekarang],
            ['id' => 2, 'no_urut' => 2, 'nama' => 'ZAENUL AHMAD, S.KOM', 'foto' => 'img/kandidat_2.jpg', 'created_at' => $sekarang, 'updated_at' => $sekarang],
            ['id' => 3, 'no_urut' => 3, 'nama' => 'YUSUF ARIPIN NURDIN', 'foto' => 'img/kandidat_3.jpg', 'created_at' => $sekarang, 'updated_at' => $sekarang],
            ['id' => 4, 'no_urut' => 4, 'nama' => 'H. BUDI SETIAWAN',    'foto' => 'img/kandidat_4.jpg', 'created_at' => $sekarang, 'updated_at' => $sekarang],
            ['id' => 5, 'no_urut' => 5, 'nama' => 'INTAN WIJAYA, SE',    'foto' => 'img/kandidat_5.jpg', 'created_at' => $sekarang, 'updated_at' => $sekarang],
        ]);
    }
}