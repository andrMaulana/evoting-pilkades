<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\KandidatSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KandidatSeederTest extends TestCase
{
    use RefreshDatabase;

    /** Daftar final (sama dengan isi menu admin pada 21 Okt 2026). */
    private const KANDIDAT = [
        1 => ['WULAN WULANDARI',      'img/kandidat_1.jpg'],
        2 => ['ZAENUL AHMAD, S.KOM',  'img/kandidat_2.jpg'],
        3 => ['YUSUF ARIPIN NURDIN',  'img/kandidat_3.jpg'],
        4 => ['H. BUDI SETIAWAN',     'img/kandidat_4.jpg'],
        5 => ['INTAN WIJAYA, SE',     'img/kandidat_5.jpg'],
    ];

    public function test_seeder_mengisi_lima_kandidat_final_dengan_id_sama_dengan_no_urut(): void
    {
        $this->seed(KandidatSeeder::class);

        $this->assertSame(5, DB::table('kandidat')->count());

        foreach (self::KANDIDAT as $no => [$nama, $foto]) {
            $baris = DB::table('kandidat')->where('id', $no)->first();
            $this->assertNotNull($baris, "kandidat $no tidak ada");
            $this->assertSame($no, (int) $baris->no_urut);
            $this->assertSame($nama, $baris->nama);
            $this->assertSame($foto, $baris->foto);
        }
    }

    public function test_file_foto_yang_dirujuk_seeder_benar_benar_ada_di_public(): void
    {
        $this->seed(KandidatSeeder::class);

        foreach (DB::table('kandidat')->pluck('foto') as $foto) {
            $this->assertFileExists(public_path($foto), "File foto tidak ditemukan: public/$foto");
        }
    }

    public function test_admin_dan_halaman_voting_menampilkan_kandidat_dan_foto_yang_sama(): void
    {
        $this->seed(KandidatSeeder::class);

        $voting = $this->get('/')->assertOk()->viewData('kandidat');
        $admin  = $this->actingAs(User::factory()->create())->get('/admin/kandidat')->assertOk()->viewData('kandidat');

        $this->assertCount(5, $voting);
        $this->assertCount(5, $admin);

        foreach ($voting as $v) {
            $a = $admin->firstWhere('id', $v['id']);
            $this->assertNotNull($a);
            $this->assertSame($v['nama'], $a->nama);
            $this->assertSame($v['foto'], $a->foto_url);
            $this->assertSame(self::KANDIDAT[$v['id']][0], $v['nama']);
        }
    }
}
