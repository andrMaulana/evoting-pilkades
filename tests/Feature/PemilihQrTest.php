<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\KodeQr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Tests\TestCase;

class PemilihQrTest extends TestCase
{
    use RefreshDatabase;

    private const NIK  = '3215010101900001';
    private const KODE = 'ABCD2345EFGH';

    private function buatPemilih(string $nik = self::NIK, ?string $kode = self::KODE, int $status = 0, int $urut = 1, int $tps = 1, string $nama = 'WARGA UJI'): void
    {
        DB::table('pemilih')->insert([
            'id' => $nik, 'nik' => $nik, 'kode_qr' => $kode, 'nama' => $nama, 'jenis_kelamin' => 'L', 'umur' => 30,
            'no_urut_dpt' => $urut, 'no_tps' => $tps, 'no_bilik' => 1, 'status_memilih' => $status,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create();
    }

    // ---------- bentuk kode ----------

    public function test_kode_qr_berformat_benar_dan_unik(): void
    {
        $semua = [];
        for ($i = 0; $i < 2000; $i++) {
            $kode = KodeQr::buat();
            $this->assertTrue(KodeQr::valid($kode), $kode);
            $semua[$kode] = true;
        }
        $this->assertCount(2000, $semua);
    }

    public function test_nik_dan_kode_salah_bentuk_dianggap_tidak_valid(): void
    {
        $this->assertFalse(KodeQr::valid(self::NIK));
        $this->assertFalse(KodeQr::valid('ABCD2345EFG'));      // kurang panjang
        $this->assertFalse(KodeQr::valid('ABCD2345EFG0'));     // mengandung 0
        $this->assertFalse(KodeQr::valid(null));
        $this->assertSame('ABCD2345EFGH', KodeQr::bersihkan('  abcd2345efgh '));
    }

    // ---------- NIK tidak bisa dipakai lagi ----------

    public function test_nik_tidak_bisa_dipakai_sebagai_qr_dan_tidak_membocorkan_data(): void
    {
        $this->buatPemilih();

        $res = $this->postJson('/api/cek-pemilih', ['qr_id' => self::NIK]);
        $res->assertStatus(404);
        $this->assertStringNotContainsString('WARGA UJI', $res->getContent());
        $this->assertStringNotContainsString('no_tps', $res->getContent());

        $this->postJson('/api/simpan-suara', ['qr_id' => self::NIK, 'kandidat_id' => null])->assertStatus(404);

        $this->assertSame(0, DB::table('suara')->count());
        $this->assertSame(0, (int) DB::table('pemilih')->value('status_memilih'));
    }

    public function test_kode_qr_valid_diterima_dan_huruf_kecil_dinormalisasi(): void
    {
        $this->buatPemilih();

        $this->postJson('/api/cek-pemilih', ['qr_id' => strtolower(self::KODE)])
            ->assertOk()->assertJson(['status' => 'ok']);

        $this->postJson('/api/simpan-suara', ['qr_id' => ' ' . strtolower(self::KODE) . ' ', 'kandidat_id' => null])
            ->assertOk();
    }

    public function test_api_dibatasi_throttle_60_per_menit(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->postJson('/api/cek-pemilih', ['qr_id' => 'SALAH'])->assertStatus(404);
        }

        $this->postJson('/api/cek-pemilih', ['qr_id' => 'SALAH'])->assertStatus(429);
    }

    // ---------- kode dibuat saat tambah/import ----------

    public function test_tambah_manual_membuat_kode_qr_acak_bukan_nik(): void
    {
        $this->actingAs($this->admin())->post('/admin/pemilih/store', [
            'nik' => self::NIK, 'no_urut_dpt' => 1, 'nama' => 'budi', 'jenis_kelamin' => 'L', 'umur' => 40,
        ])->assertRedirect();

        $kode = DB::table('pemilih')->where('nik', self::NIK)->value('kode_qr');
        $this->assertTrue(KodeQr::valid($kode));
        $this->assertNotSame(self::NIK, $kode);
    }

    public function test_import_csv_membuat_kode_unik_dan_tidak_mengubah_kode_saat_diimpor_ulang(): void
    {
        $csv = "NO;NAMA;L;P;USIA;A;B;C;NIK\n"
             . "1;BUDI;L;;40;x;x;x;3215010101900011\n"
             . "2;SITI;;P;35;x;x;x;3215010101900012\n";
        $file = fn () => UploadedFile::fake()->createWithContent('dpt.csv', $csv);

        $this->actingAs($this->admin());
        $this->post('/admin/pemilih/import', ['file_csv' => $file()])->assertRedirect();

        $kode = DB::table('pemilih')->pluck('kode_qr', 'nik');
        $this->assertCount(2, $kode);
        foreach ($kode as $nik => $k) {
            $this->assertTrue(KodeQr::valid($k));
            $this->assertNotSame($nik, $k);
        }
        $this->assertCount(2, array_unique($kode->all()));

        // impor file yang sama lagi: baris lama diabaikan, kode lama tetap
        $this->post('/admin/pemilih/import', ['file_csv' => $file()])->assertRedirect();
        $this->assertSame($kode->all(), DB::table('pemilih')->pluck('kode_qr', 'nik')->all());
    }

    // ---------- terbitkan ulang QR ----------

    public function test_qr_baru_mengganti_kode_dan_kode_lama_langsung_tidak_berlaku(): void
    {
        $this->buatPemilih();

        $this->actingAs($this->admin())
            ->post('/admin/pemilih/qr-baru/' . self::NIK)
            ->assertRedirect()->assertSessionHas('success');

        $baru = DB::table('pemilih')->where('nik', self::NIK)->value('kode_qr');
        $this->assertNotSame(self::KODE, $baru);
        $this->assertTrue(KodeQr::valid($baru));

        $this->postJson('/api/cek-pemilih', ['qr_id' => self::KODE])->assertStatus(404);
        $this->postJson('/api/cek-pemilih', ['qr_id' => $baru])->assertOk();
    }

    public function test_qr_baru_ditolak_untuk_pemilih_yang_sudah_memilih(): void
    {
        $this->buatPemilih(status: 1);

        $this->actingAs($this->admin())
            ->post('/admin/pemilih/qr-baru/' . self::NIK)
            ->assertRedirect()->assertSessionHas('error');

        $this->assertSame(self::KODE, DB::table('pemilih')->value('kode_qr'));
    }

    public function test_qr_baru_dan_cetak_massal_butuh_login(): void
    {
        $this->buatPemilih();

        $this->post('/admin/pemilih/qr-baru/' . self::NIK)->assertRedirect('/login');
        $this->get('/admin/pemilih/cetak-massal')->assertRedirect('/login');
        $this->assertSame(self::KODE, DB::table('pemilih')->value('kode_qr'));
    }

    // ---------- cetak undangan ----------

    public function test_undangan_memuat_qr_dari_kode_bukan_dari_nik(): void
    {
        $this->buatPemilih();

        $this->actingAs($this->admin())
            ->get('/admin/pemilih/cetak/' . self::NIK)
            ->assertOk()
            ->assertSee(QrCode::size(120)->generate(self::KODE), false)
            ->assertDontSee(QrCode::size(120)->generate(self::NIK), false);
    }

    public function test_daftar_dpt_tidak_menampilkan_kode_qr(): void
    {
        $this->buatPemilih();

        $this->actingAs($this->admin())
            ->get('/admin/pemilih')
            ->assertOk()
            ->assertSee('WARGA UJI')
            ->assertDontSee(self::KODE);
    }

    public function test_cetak_massal_menghormati_filter_tps_dan_rentang_urut(): void
    {
        $this->buatPemilih('3215010101900001', 'AAAA2222BBBB', 0, 1, 1, 'SATU');
        $this->buatPemilih('3215010101900002', 'CCCC3333DDDD', 0, 2, 1, 'DUA');
        $this->buatPemilih('3215010101900003', 'EEEE4444FFFF', 0, 3, 2, 'TIGA');
        $this->actingAs($this->admin());

        $hitung = fn ($html) => substr_count($html, 'SURAT PANGGILAN PEMUNGUTAN SUARA');

        $semua = $this->get('/admin/pemilih/cetak-massal')->assertOk()->getContent();
        $this->assertSame(3, $hitung($semua));

        $tps2 = $this->get('/admin/pemilih/cetak-massal?tps=2')->assertOk()->assertSee('TIGA')->assertDontSee('SATU')->getContent();
        $this->assertSame(1, $hitung($tps2));

        $rentang = $this->get('/admin/pemilih/cetak-massal?dari=2&sampai=3')->assertOk()->getContent();
        $this->assertSame(2, $hitung($rentang));

        $kosong = $this->get('/admin/pemilih/cetak-massal?tps=9')->assertOk()->assertSee('Tidak ada pemilih')->getContent();
        $this->assertSame(0, $hitung($kosong));
    }

    public function test_cetak_massal_dan_cetak_satuan_membuatkan_kode_yang_masih_kosong(): void
    {
        $this->buatPemilih('3215010101900001', null, 0, 1, 1, 'SATU');
        $this->buatPemilih('3215010101900002', null, 0, 2, 1, 'DUA');
        $this->actingAs($this->admin());

        $this->get('/admin/pemilih/cetak-massal')->assertOk();

        foreach (DB::table('pemilih')->pluck('kode_qr') as $kode) {
            $this->assertTrue(KodeQr::valid($kode));
        }
    }
}
