<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\FotoKandidat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KandidatDariDatabaseTest extends TestCase
{
    use RefreshDatabase;

    /** Sengaja id TIDAK sama dengan no_urut, untuk membuktikan pemetaan tidak bergantung kebetulan. */
    private function buatKandidat(): void
    {
        DB::table('kandidat')->insert([
            ['id' => 10, 'no_urut' => 1, 'nama' => 'CALON SATU', 'foto' => null,                  'created_at' => now(), 'updated_at' => now()],
            ['id' => 20, 'no_urut' => 2, 'nama' => 'CALON DUA',  'foto' => 'img/kandidat_2.jpg',   'created_at' => now(), 'updated_at' => now()],
            ['id' => 30, 'no_urut' => 3, 'nama' => 'CALON TIGA', 'foto' => 'abc123.jpg',           'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function buatPemilih(string $nik, string $kode): void
    {
        DB::table('pemilih')->insert([
            'id' => $nik, 'nik' => $nik, 'kode_qr' => $kode, 'nama' => 'WARGA UJI', 'jenis_kelamin' => 'L', 'umur' => 30,
            'no_urut_dpt' => 1, 'no_tps' => 1, 'no_bilik' => 1, 'status_memilih' => 0,
        ]);
    }

    public function test_halaman_voting_memuat_kandidat_dari_database_berurutan_no_urut(): void
    {
        $this->buatKandidat();

        $this->get('/')
            ->assertOk()
            ->assertSee('CALON SATU')
            ->assertViewHas('kandidat', function ($k) {
                return $k->count() === 3
                    && $k[0]['id'] === 10 && $k[0]['no'] === 1 && $k[0]['foto'] === null
                    && $k[1]['id'] === 20 && $k[1]['foto'] === asset('img/kandidat_2.jpg')
                    && $k[2]['id'] === 30 && $k[2]['foto'] === asset('storage/kandidat/abc123.jpg');
            });
    }

    public function test_halaman_voting_tetap_tampil_saat_belum_ada_kandidat(): void
    {
        $this->get('/')->assertOk()->assertViewHas('kandidat', fn ($k) => $k->isEmpty());
    }

    public function test_suara_untuk_id_kandidat_masuk_ke_kandidat_yang_benar_di_rekap(): void
    {
        $this->buatKandidat();
        $this->buatPemilih('3215010101900001', 'KDEF2345ABCD');

        // frontend sekarang mengirim id database (20), bukan no_urut (2)
        $this->postJson('/api/simpan-suara', ['qr_id' => 'KDEF2345ABCD', 'kandidat_id' => 20])->assertOk();

        $rekap = DB::table('kandidat')
            ->leftJoin('suara', 'kandidat.id', '=', 'suara.kandidat_id')
            ->select('kandidat.nama', DB::raw('count(suara.id) as total'))
            ->groupBy('kandidat.id', 'kandidat.nama')
            ->pluck('total', 'nama');

        $this->assertSame(1, (int) $rekap['CALON DUA']);
        $this->assertSame(0, (int) $rekap['CALON SATU']);
    }

    public function test_no_urut_yang_bukan_id_ditolak_bukan_masuk_ke_kandidat_lain(): void
    {
        $this->buatKandidat();                       // id yang ada: 10, 20, 30
        $this->buatPemilih('3215010101900002', 'KDEF6789EFGH');

        $this->postJson('/api/simpan-suara', ['qr_id' => 'KDEF6789EFGH', 'kandidat_id' => 2])
            ->assertStatus(422);                     // "2" adalah no_urut, bukan id

        $this->assertSame(0, DB::table('suara')->count());
    }

    public function test_upload_foto_admin_tersimpan_di_disk_public_dan_tampil_di_halaman_admin(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());

        $this->post('/admin/kandidat/store', [
            'no_urut' => 4,
            'nama'    => "ma'ruf amin",
            'foto'    => UploadedFile::fake()->image('wajah saya.jpg', 300, 400),
        ])->assertRedirect();

        $foto = DB::table('kandidat')->where('no_urut', 4)->value('foto');
        $this->assertNotNull($foto);
        $this->assertStringNotContainsString('/', $foto);          // hanya nama file
        Storage::disk('public')->assertExists('kandidat/' . $foto);

        $this->get('/admin/kandidat')->assertOk()->assertSee(FotoKandidat::url($foto), false);
        $this->get('/')->assertOk()->assertViewHas('kandidat', fn ($k) => $k[0]['foto'] === FotoKandidat::url($foto));
    }

    public function test_ganti_foto_menghapus_file_lama_dan_hapus_kandidat_menghapus_file(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());

        Storage::disk('public')->put('kandidat/lama.jpg', 'x');
        DB::table('kandidat')->insert(['id' => 5, 'no_urut' => 5, 'nama' => 'X', 'foto' => 'lama.jpg', 'created_at' => now(), 'updated_at' => now()]);

        $this->post('/admin/kandidat/update/5', [
            '_method' => 'PUT', 'no_urut' => 5, 'nama' => 'X',
            'foto' => UploadedFile::fake()->image('baru.png', 300, 400),
        ])->assertRedirect();

        $baru = DB::table('kandidat')->where('id', 5)->value('foto');
        $this->assertNotSame('lama.jpg', $baru);
        Storage::disk('public')->assertMissing('kandidat/lama.jpg');
        Storage::disk('public')->assertExists('kandidat/' . $baru);

        $this->post('/admin/kandidat/delete/5', ['_method' => 'DELETE'])->assertRedirect();
        Storage::disk('public')->assertMissing('kandidat/' . $baru);
        $this->assertSame(0, DB::table('kandidat')->count());
    }

    public function test_foto_bawaan_seeder_di_public_img_tidak_ikut_terhapus(): void
    {
        FotoKandidat::hapus('img/kandidat_1.jpg');      // tidak boleh error / menyentuh disk
        $this->assertNull(FotoKandidat::url(null));
        $this->assertSame(asset('img/kandidat_1.jpg'), FotoKandidat::url('img/kandidat_1.jpg'));
    }
}
