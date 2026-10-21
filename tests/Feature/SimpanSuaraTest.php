<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SimpanSuaraTest extends TestCase
{
    use RefreshDatabase;

    private string $nik = '3215010101900001';
    private string $kode = 'ABCD2345EFGH';   // isi QR kartu (bukan NIK)

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([1, 2, 3] as $n) {
            DB::table('kandidat')->insert([
                'id' => $n, 'no_urut' => $n, 'nama' => "KANDIDAT $n",
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        DB::table('pemilih')->insert([
            'id' => $this->nik, 'nik' => $this->nik, 'kode_qr' => $this->kode, 'nama' => 'WARGA UJI',
            'jenis_kelamin' => 'L', 'umur' => 30, 'no_urut_dpt' => 1,
            'no_tps' => 1, 'no_bilik' => 1, 'status_memilih' => 0,
        ]);
    }

    private function kirim(array $data)
    {
        return $this->postJson('/api/simpan-suara', $data);
    }

    public function test_suara_sah_tersimpan_dan_pemilih_ditandai(): void
    {
        $this->kirim(['qr_id' => $this->kode, 'kandidat_id' => 3])
            ->assertOk()->assertJson(['status' => 'sukses']);

        $this->assertSame(1, DB::table('suara')->where('kandidat_id', 3)->count());
        $this->assertSame(1, (int) DB::table('pemilih')->where('id', $this->nik)->value('status_memilih'));
    }

    public function test_suara_tidak_sah_tersimpan_dengan_kandidat_null(): void
    {
        $this->kirim(['qr_id' => $this->kode, 'kandidat_id' => null])->assertOk();

        $this->assertSame(1, DB::table('suara')->whereNull('kandidat_id')->count());
    }

    public function test_pemilih_yang_sudah_memilih_ditolak_dan_suara_tidak_bertambah(): void
    {
        $this->kirim(['qr_id' => $this->kode, 'kandidat_id' => 1])->assertOk();

        $this->kirim(['qr_id' => $this->kode, 'kandidat_id' => 2])
            ->assertStatus(403)->assertJson(['status' => 'sudah_memilih']);

        $this->assertSame(1, DB::table('suara')->count());
    }

    public function test_klik_ganda_beruntun_hanya_menghasilkan_satu_suara(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->kirim(['qr_id' => $this->kode, 'kandidat_id' => 2]);
        }

        $this->assertSame(1, DB::table('suara')->count());
    }

    public function test_qr_tidak_terdaftar_ditolak_tanpa_menambah_suara(): void
    {
        $this->kirim(['qr_id' => 'ZZZZ9999ZZZZ', 'kandidat_id' => 1])
            ->assertStatus(404);

        $this->assertSame(0, DB::table('suara')->count());
    }

    public function test_kandidat_yang_tidak_ada_ditolak_dan_pemilih_tidak_berubah(): void
    {
        $this->kirim(['qr_id' => $this->kode, 'kandidat_id' => 99])->assertStatus(422);

        $this->assertSame(0, DB::table('suara')->count());
        $this->assertSame(0, (int) DB::table('pemilih')->where('id', $this->nik)->value('status_memilih'));
    }

    public function test_qr_id_kosong_ditolak_dengan_json(): void
    {
        // tanpa header Accept: JSON, seperti fetch() di halaman voting
        $this->call('POST', '/api/simpan-suara', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['kandidat_id' => 1]))
            ->assertStatus(422)->assertJson(['status' => 'error']);
    }

    public function test_error_server_di_rollback_dan_pesan_tidak_membocorkan_detail(): void
    {
        Schema::drop('suara');   // paksa INSERT suara gagal

        $res = $this->kirim(['qr_id' => $this->kode, 'kandidat_id' => 1]);

        $res->assertStatus(500);
        $this->assertStringNotContainsStringIgnoringCase('SQLSTATE', $res->getContent());
        $this->assertStringNotContainsStringIgnoringCase('no such table', $res->getContent());
        // status pemilih harus kembali (rollback), supaya bisa mencoba lagi
        $this->assertSame(0, (int) DB::table('pemilih')->where('id', $this->nik)->value('status_memilih'));
    }
}
