<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Pengaturan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TpsBilikTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    /** Isi $jumlah pemilih dengan no_urut_dpt 1..$jumlah (semua TPS 1 / bilik 1, seperti hasil import). */
    private function isiPemilih(int $jumlah, string $namaPertama = 'WARGA 1'): void
    {
        $baris = [];
        for ($i = 1; $i <= $jumlah; $i++) {
            $nik = '3215' . str_pad((string) $i, 12, '0', STR_PAD_LEFT);
            $baris[] = [
                'id' => $nik, 'nik' => $nik, 'kode_qr' => strtoupper(substr(md5('kode' . $i), 0, 12)),   // unik per pemilih
                'nama' => $i === 1 ? $namaPertama : "WARGA $i", 'jenis_kelamin' => 'L', 'umur' => 30,
                'no_urut_dpt' => $i, 'no_tps' => 1, 'no_bilik' => 1, 'status_memilih' => 0,
            ];
        }
        foreach (array_chunk($baris, 400) as $potong) {
            DB::table('pemilih')->insert($potong);
        }
    }

    private function sebaran(): array
    {
        return DB::table('pemilih')->select('no_tps', 'no_bilik', DB::raw('count(*) as n'))
            ->groupBy('no_tps', 'no_bilik')->orderBy('no_tps')->orderBy('no_bilik')->get()
            ->map(fn ($r) => "{$r->no_tps}/{$r->no_bilik}={$r->n}")->all();
    }

    // ---------- edit satu per satu ----------

    public function test_tambah_manual_menyimpan_tps_dan_bilik_atau_bawaan_1(): void
    {
        $this->actingAs($this->admin());
        $dasar = ['no_urut_dpt' => 1, 'nama' => 'budi', 'jenis_kelamin' => 'L', 'umur' => 40];

        $this->post('/admin/pemilih/store', $dasar + ['nik' => '3215000000000001', 'no_tps' => 3, 'no_bilik' => 2])->assertSessionHasNoErrors();
        $this->post('/admin/pemilih/store', ['no_urut_dpt' => 2, 'nik' => '3215000000000002'] + $dasar)->assertSessionHasNoErrors();

        $a = DB::table('pemilih')->where('nik', '3215000000000001')->first();
        $b = DB::table('pemilih')->where('nik', '3215000000000002')->first();
        $this->assertSame([3, 2], [(int) $a->no_tps, (int) $a->no_bilik]);
        $this->assertSame([1, 1], [(int) $b->no_tps, (int) $b->no_bilik]);
    }

    public function test_tambah_dan_edit_menolak_tps_bilik_di_luar_1_sampai_99(): void
    {
        $this->actingAs($this->admin());
        $this->isiPemilih(1);
        $dasar = ['nik' => '3215000000000099', 'no_urut_dpt' => 99, 'nama' => 'budi', 'jenis_kelamin' => 'L', 'umur' => 40];

        $this->post('/admin/pemilih/store', $dasar + ['no_tps' => 0])->assertSessionHasErrors('no_tps');
        $this->post('/admin/pemilih/store', $dasar + ['no_bilik' => 100])->assertSessionHasErrors('no_bilik');
        $this->assertSame(1, DB::table('pemilih')->count());

        $nik = '3215' . str_pad('1', 12, '0', STR_PAD_LEFT);
        $this->post("/admin/pemilih/update/$nik", ['_method' => 'PUT', 'nik' => $nik, 'no_urut_dpt' => 1, 'nama' => 'x', 'jenis_kelamin' => 'L', 'umur' => 30, 'no_tps' => 200])
            ->assertSessionHasErrors('no_tps');
    }

    public function test_edit_mengubah_tps_bilik_dan_form_lama_tanpa_kolom_itu_tidak_mereset(): void
    {
        $this->actingAs($this->admin());
        $this->isiPemilih(1);
        $nik = '3215' . str_pad('1', 12, '0', STR_PAD_LEFT);
        $dasar = ['_method' => 'PUT', 'nik' => $nik, 'no_urut_dpt' => 1, 'nama' => 'warga satu', 'jenis_kelamin' => 'L', 'umur' => 30];

        $this->post("/admin/pemilih/update/$nik", $dasar + ['no_tps' => 4, 'no_bilik' => 2])->assertSessionHasNoErrors();
        $p = DB::table('pemilih')->where('nik', $nik)->first();
        $this->assertSame([4, 2], [(int) $p->no_tps, (int) $p->no_bilik]);

        $this->post("/admin/pemilih/update/$nik", $dasar)->assertSessionHasNoErrors();   // tanpa kolom TPS/bilik
        $p = DB::table('pemilih')->where('nik', $nik)->first();
        $this->assertSame([4, 2], [(int) $p->no_tps, (int) $p->no_bilik]);
    }

    public function test_tabel_dpt_menampilkan_tps_bilik_dan_tombol_edit_aman_untuk_nama_berapostrof(): void
    {
        $this->isiPemilih(1, "H. MA'RUF");
        DB::table('pemilih')->update(['no_tps' => 2, 'no_bilik' => 3]);

        $res = $this->actingAs($this->admin())->get('/admin/pemilih')->assertOk();

        $res->assertSee('TPS / Bilik')->assertSee('2 / 3');
        // nama dikirim ke JavaScript lewat JSON ter-escape, bukan di dalam string berapostrof
        $res->assertDontSee("'H. MA'RUF'", false);
        $res->assertSee('u0027RUF', false);
    }

    // ---------- atur massal ----------

    public function test_atur_massal_sesuai_pola_1_tps_2_bilik_per_rentang_no_urut(): void
    {
        $this->isiPemilih(1500);

        $this->actingAs($this->admin())->post('/admin/pemilih/atur-tps', ['aturan' => [
            ['dari' => 1,    'sampai' => 500,  'tps' => 1, 'bilik' => 1],
            ['dari' => 501,  'sampai' => 1000, 'tps' => 1, 'bilik' => 2],
            ['dari' => 1001, 'sampai' => 1250, 'tps' => 2, 'bilik' => 1],
        ]])->assertRedirect()->assertSessionHas('success');

        // 1251-1500 tidak ada di aturan: tetap TPS 1 bilik 1 (nilai awal), jadi 1/1 = 500 + 250 = 750
        $hasil = DB::table('pemilih')->select('no_tps', 'no_bilik', DB::raw('count(*) as n'))->groupBy('no_tps', 'no_bilik')->get()
            ->mapWithKeys(fn ($r) => ["{$r->no_tps}/{$r->no_bilik}" => (int) $r->n]);
        $this->assertSame(['1/1' => 750, '1/2' => 500, '2/1' => 250], $hasil->sortKeys()->all());

        // batas rentang tepat
        $bilik = fn (int $urut) => (int) DB::table('pemilih')->where('no_urut_dpt', $urut)->value('no_bilik');
        $this->assertSame([1, 1, 2, 2], [$bilik(1), $bilik(500), $bilik(501), $bilik(1000)]);
    }

    public function test_pesan_hasil_menyebut_jumlah_per_aturan(): void
    {
        $this->isiPemilih(10);

        $res = $this->actingAs($this->admin())->post('/admin/pemilih/atur-tps', ['aturan' => [
            ['dari' => 1, 'sampai' => 4, 'tps' => 1, 'bilik' => 1],
            ['dari' => 5, 'sampai' => 10, 'tps' => 1, 'bilik' => 2],
        ]]);

        $pesan = session('success');
        $this->assertStringContainsString('10 pemilih diatur', $pesan);
        $this->assertStringContainsString('TPS 1 bilik 1: 4', $pesan);
        $this->assertStringContainsString('TPS 1 bilik 2: 6', $pesan);
    }

    public function test_atur_massal_menolak_rentang_tumpang_tindih_dan_tidak_mengubah_apa_pun(): void
    {
        $this->isiPemilih(20);
        $sebelum = $this->sebaran();

        $this->actingAs($this->admin())->post('/admin/pemilih/atur-tps', ['aturan' => [
            ['dari' => 1,  'sampai' => 10, 'tps' => 2, 'bilik' => 1],
            ['dari' => 10, 'sampai' => 20, 'tps' => 3, 'bilik' => 1],
        ]])->assertSessionHas('error');

        $this->assertStringContainsString('tumpang tindih', session('error'));
        $this->assertSame($sebelum, $this->sebaran());
    }

    public function test_atur_massal_menolak_input_tidak_valid(): void
    {
        $this->isiPemilih(20);
        $sebelum = $this->sebaran();
        $this->actingAs($this->admin());

        $salah = [
            [['dari' => 10, 'sampai' => 5, 'tps' => 1, 'bilik' => 1]],      // sampai < dari
            [['dari' => 1, 'sampai' => 5, 'tps' => 0, 'bilik' => 1]],       // TPS 0
            [['dari' => 1, 'sampai' => 5, 'tps' => 1, 'bilik' => 100]],     // bilik 100
            [['dari' => 'a', 'sampai' => 5, 'tps' => 1, 'bilik' => 1]],     // bukan angka
            [['dari' => 1, 'sampai' => 5, 'tps' => 1]],                     // bilik kosong
        ];
        foreach ($salah as $aturan) {
            $this->post('/admin/pemilih/atur-tps', ['aturan' => $aturan])->assertSessionHas('error');
        }

        $this->post('/admin/pemilih/atur-tps', ['aturan' => []])->assertSessionHas('error');
        $this->post('/admin/pemilih/atur-tps', [])->assertSessionHas('error');
        $this->assertSame($sebelum, $this->sebaran());
    }

    public function test_baris_kosong_diabaikan_dan_rentang_tanpa_pemilih_diberi_tahu(): void
    {
        $this->isiPemilih(10);
        $this->actingAs($this->admin());

        $this->post('/admin/pemilih/atur-tps', ['aturan' => [
            ['dari' => '', 'sampai' => '', 'tps' => '', 'bilik' => ''],
            ['dari' => 1, 'sampai' => 3, 'tps' => 2, 'bilik' => 2],
        ]])->assertSessionHas('success');
        $this->assertSame(3, DB::table('pemilih')->where('no_tps', 2)->count());

        $this->post('/admin/pemilih/atur-tps', ['aturan' => [['dari' => 500, 'sampai' => 600, 'tps' => 5, 'bilik' => 1]]])
            ->assertSessionHas('error');
        $this->assertStringContainsString('Tidak ada pemilih', session('error'));
    }

    public function test_filter_cetak_massal_per_tps_sekarang_berfungsi_dan_undangan_memuat_bilik(): void
    {
        $this->isiPemilih(6);
        $admin = $this->actingAs($this->admin());
        $admin->post('/admin/pemilih/atur-tps', ['aturan' => [
            ['dari' => 1, 'sampai' => 2, 'tps' => 1, 'bilik' => 1],
            ['dari' => 3, 'sampai' => 6, 'tps' => 3, 'bilik' => 2],
        ]]);

        $hitung = fn ($html) => substr_count($html, 'SURAT PANGGILAN PEMUNGUTAN SUARA');

        $tps3 = $this->get('/admin/pemilih/cetak-massal?tps=3')->assertOk();
        $this->assertSame(4, $hitung($tps3->getContent()));
        $tps3->assertSee('TPS 03')->assertSee('Bilik 2')->assertSee('Bilik Suara');
        $this->assertSame(2, $hitung($this->get('/admin/pemilih/cetak-massal?tps=1')->getContent()));

        $nik = '3215' . str_pad('1', 12, '0', STR_PAD_LEFT);
        $this->get("/admin/pemilih/cetak/$nik")->assertOk()->assertSee('TPS 01')->assertSee('Bilik 1');
    }

    public function test_halaman_dpt_menampilkan_sebaran_saat_ini(): void
    {
        $this->isiPemilih(6);
        DB::table('pemilih')->where('no_urut_dpt', '>', 4)->update(['no_tps' => 2, 'no_bilik' => 2]);

        $this->actingAs($this->admin())->get('/admin/pemilih')->assertOk()
            ->assertSee('Sebaran saat ini')
            ->assertSee('TPS 1 · Bilik 1: ', false)
            ->assertSee('TPS 2 · Bilik 2: ', false);
    }

    public function test_atur_massal_dan_kunci_kiosk_butuh_login(): void
    {
        $this->isiPemilih(3);

        $this->post('/admin/pemilih/atur-tps', ['aturan' => [['dari' => 1, 'sampai' => 3, 'tps' => 9, 'bilik' => 9]]])->assertRedirect('/login');
        $this->post('/admin/pemilih/kunci-kiosk', ['tps' => 2])->assertRedirect('/login');
        $this->assertSame(['1/1=3'], $this->sebaran());
        $this->assertNull(Pengaturan::tpsKiosk());
    }

    // ---------- kunci kiosk ----------

    private function pemilihDiTps(int $tps, int $bilik, string $kode = 'ABCD2345EFGH', int $status = 0): void
    {
        DB::table('pemilih')->insert([
            'id' => '3215010101900001', 'nik' => '3215010101900001', 'kode_qr' => $kode, 'nama' => 'WARGA UJI',
            'jenis_kelamin' => 'L', 'umur' => 30, 'no_urut_dpt' => 1, 'no_tps' => $tps, 'no_bilik' => $bilik, 'status_memilih' => $status,
        ]);
    }

    public function test_tanpa_kunci_semua_tps_dilayani_seperti_sebelumnya(): void
    {
        $this->pemilihDiTps(3, 2);

        $this->postJson('/api/cek-pemilih', ['qr_id' => 'ABCD2345EFGH'])->assertOk()->assertJson(['status' => 'ok']);
        $this->get('/')->assertOk()->assertDontSee('· TPS');
    }

    public function test_kunci_kiosk_tersimpan_dan_tampil_di_header_layar_voting(): void
    {
        $this->actingAs($this->admin())->post('/admin/pemilih/kunci-kiosk', ['tps' => 2])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame(2, Pengaturan::tpsKiosk());
        auth()->logout();
        $this->get('/')->assertOk()->assertSee('Pemilihan Kepala Desa · TPS 02', false);
    }

    public function test_kiosk_terkunci_menolak_pemilih_tps_lain_dengan_info_tps_dan_bilik(): void
    {
        $this->pemilihDiTps(3, 2);
        Pengaturan::setTpsKiosk(1);

        $res = $this->postJson('/api/cek-pemilih', ['qr_id' => 'ABCD2345EFGH'])->assertStatus(403)->assertJson(['status' => 'beda_tps']);

        $this->assertStringContainsString('TPS 03', $res->json('pesan'));
        $this->assertStringContainsString('Bilik 2', $res->json('pesan'));
    }

    public function test_kiosk_terkunci_menerima_pemilih_dari_tps_yang_sama(): void
    {
        $this->pemilihDiTps(2, 1);
        Pengaturan::setTpsKiosk(2);

        $this->postJson('/api/cek-pemilih', ['qr_id' => 'ABCD2345EFGH'])->assertOk()->assertJson(['status' => 'ok']);
        $this->postJson('/api/simpan-suara', ['qr_id' => 'ABCD2345EFGH', 'kandidat_id' => null])->assertOk();
        $this->assertSame(1, DB::table('suara')->count());
    }

    public function test_simpan_suara_menolak_tps_lain_walau_cek_pemilih_dilewati(): void
    {
        $this->pemilihDiTps(3, 1);
        Pengaturan::setTpsKiosk(1);

        // penyerang memanggil API simpan langsung, tanpa lewat layar voting
        $this->postJson('/api/simpan-suara', ['qr_id' => 'ABCD2345EFGH', 'kandidat_id' => null])
            ->assertStatus(403)->assertJson(['status' => 'beda_tps']);

        $this->assertSame(0, DB::table('suara')->count());
        $this->assertSame(0, (int) DB::table('pemilih')->value('status_memilih'));   // status tidak berubah, masih bisa memilih di TPS-nya
    }

    public function test_pemilih_yang_sudah_memilih_tetap_dijawab_sudah_memilih_walau_beda_tps(): void
    {
        $this->pemilihDiTps(3, 1, status: 1);
        Pengaturan::setTpsKiosk(1);

        $this->postJson('/api/cek-pemilih', ['qr_id' => 'ABCD2345EFGH'])->assertStatus(403)->assertJson(['status' => 'sudah_memilih']);
        $this->postJson('/api/simpan-suara', ['qr_id' => 'ABCD2345EFGH', 'kandidat_id' => null])->assertStatus(403)->assertJson(['status' => 'sudah_memilih']);
    }

    public function test_kunci_bisa_dibuka_lagi_dan_input_tidak_valid_ditolak(): void
    {
        $this->pemilihDiTps(3, 1);
        $admin = $this->actingAs($this->admin());

        $admin->post('/admin/pemilih/kunci-kiosk', ['tps' => 1]);
        $this->postJson('/api/cek-pemilih', ['qr_id' => 'ABCD2345EFGH'])->assertStatus(403);

        $admin->post('/admin/pemilih/kunci-kiosk', ['tps' => ''])->assertSessionHas('success');   // kosong = semua TPS
        $this->assertNull(Pengaturan::tpsKiosk());
        $this->postJson('/api/cek-pemilih', ['qr_id' => 'ABCD2345EFGH'])->assertOk();

        $admin->post('/admin/pemilih/kunci-kiosk', ['tps' => 2]);
        foreach (['0', '100', 'abc'] as $salah) {
            $admin->post('/admin/pemilih/kunci-kiosk', ['tps' => $salah])->assertSessionHas('error');
        }
        $this->assertSame(2, Pengaturan::tpsKiosk());   // nilai terakhir yang valid tidak berubah
    }

    public function test_layar_voting_tetap_jalan_dan_kunci_gagal_dengan_pesan_jelas_kalau_tabel_pengaturan_belum_ada(): void
    {
        $this->pemilihDiTps(3, 1);
        Schema::drop('pengaturan');
        Pengaturan::lupakanCache();

        $this->postJson('/api/cek-pemilih', ['qr_id' => 'ABCD2345EFGH'])->assertOk();   // tanpa tabel = tanpa kunci
        $this->get('/')->assertOk();

        $this->actingAs($this->admin())->post('/admin/pemilih/kunci-kiosk', ['tps' => 1])->assertSessionHas('error');
        $this->assertStringContainsString('php artisan migrate', session('error'));

        Pengaturan::lupakanCache();
    }
}
