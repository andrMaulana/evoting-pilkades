<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Pengaturan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardRealtimeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    /** 3 kandidat, 5 pemilih (2 sudah memilih: 1 L + 1 P), 4 suara (3 sah + 1 batal) yang sengaja tidak sinkron dengan status pemilih. */
    private function isiData(): void
    {
        foreach ([1 => 'SATU', 2 => 'DUA', 3 => 'TIGA'] as $id => $nama) {
            DB::table('kandidat')->insert(['id' => $id, 'no_urut' => $id, 'nama' => $nama, 'created_at' => now(), 'updated_at' => now()]);
        }

        $pemilih = [
            ['3215010101900001', 'AAAA2222BBBB', 'L', 1],
            ['3215010101900002', 'CCCC3333DDDD', 'P', 1],
            ['3215010101900003', 'EEEE4444FFFF', 'L', 0],
            ['3215010101900004', 'GGGG5555HHHH', 'P', 0],
            ['3215010101900005', 'JJJJ6666KKKK', 'L', 0],
        ];
        foreach ($pemilih as $i => [$nik, $kode, $jk, $status]) {
            DB::table('pemilih')->insert([
                'id' => $nik, 'nik' => $nik, 'kode_qr' => $kode, 'nama' => "WARGA $i", 'jenis_kelamin' => $jk,
                'umur' => 30, 'no_urut_dpt' => $i + 1, 'no_tps' => 1, 'no_bilik' => 1, 'status_memilih' => $status,
            ]);
        }

        foreach ([1, 1, 2, null] as $kandidatId) {
            DB::table('suara')->insert(['kandidat_id' => $kandidatId, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    // ---------- akses ----------

    public function test_data_dashboard_butuh_login(): void
    {
        $this->getJson('/admin/dashboard/data')->assertStatus(401);
        $this->get('/admin/dashboard/data')->assertRedirect('/login');
        $this->postJson('/admin/dashboard/sembunyikan', ['sembunyikan' => true])->assertStatus(401);
    }

    // ---------- isi data ----------

    public function test_data_berisi_angka_yang_benar_dan_tidak_di_cache(): void
    {
        $this->isiData();

        $res = $this->actingAs($this->admin())->getJson('/admin/dashboard/data')->assertOk();

        $res->assertJson([
            'totalDpt' => 5, 'sudahMilih' => 2, 'belumMilih' => 3,
            'pemilihLaki' => 1, 'pemilihPerempuan' => 1,
            'suaraSah' => 3, 'suaraTidakSah' => 1,
            'tersembunyi' => false,
        ]);

        $kandidat = collect($res->json('kandidat'))->keyBy('id');
        $this->assertSame(2, $kandidat[1]['total_suara']);
        $this->assertSame(66.7, (float) $kandidat[1]['persen']);   // 2 dari 3 suara sah
        $this->assertSame(1, $kandidat[2]['total_suara']);
        $this->assertSame(33.3, (float) $kandidat[2]['persen']);
        $this->assertSame(0, $kandidat[3]['total_suara']);
        $this->assertSame(['SATU', 'DUA', 'TIGA'], collect($res->json('kandidat'))->pluck('nama')->all());   // urut no_urut
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2}$/', $res->json('diperbarui'));

        $this->assertStringContainsString('no-store', $res->headers->get('Cache-Control'));
    }

    public function test_data_kosong_tidak_membuat_error_dan_persen_tidak_dibagi_nol(): void
    {
        DB::table('kandidat')->insert(['id' => 1, 'no_urut' => 1, 'nama' => 'SATU', 'created_at' => now(), 'updated_at' => now()]);

        $res = $this->actingAs($this->admin())->getJson('/admin/dashboard/data')->assertOk();

        $res->assertJson(['totalDpt' => 0, 'sudahMilih' => 0, 'belumMilih' => 0, 'suaraSah' => 0, 'suaraTidakSah' => 0]);
        $this->assertSame(0, $res->json('kandidat.0.total_suara'));
        $this->assertEquals(0, $res->json('kandidat.0.persen'));
    }

    public function test_angka_realtime_berubah_setelah_ada_suara_masuk(): void
    {
        $this->isiData();
        $admin = $this->admin();

        $sebelum = $this->actingAs($admin)->getJson('/admin/dashboard/data')->json();

        // pemilih ke-3 mencoblos kandidat 3 lewat API yang sama dengan layar voting
        $this->postJson('/api/simpan-suara', ['qr_id' => 'EEEE4444FFFF', 'kandidat_id' => 3])->assertOk();

        $sesudah = $this->actingAs($admin)->getJson('/admin/dashboard/data')->json();

        $this->assertSame($sebelum['sudahMilih'] + 1, $sesudah['sudahMilih']);
        $this->assertSame($sebelum['belumMilih'] - 1, $sesudah['belumMilih']);
        $this->assertSame($sebelum['suaraSah'] + 1, $sesudah['suaraSah']);
        $this->assertSame($sebelum['pemilihLaki'] + 1, $sesudah['pemilihLaki']);
        $this->assertSame(1, collect($sesudah['kandidat'])->firstWhere('id', 3)['total_suara']);
    }

    // ---------- halaman ----------

    public function test_halaman_dashboard_menampilkan_angka_awal_dan_memuat_polling_3_detik(): void
    {
        $this->isiData();

        $this->actingAs($this->admin())->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('data-stat="totalDpt">5<', false)
            ->assertSee('data-stat="sudahMilih">2<', false)
            ->assertSee('const SELANG_MS    = 3000;', false)
            ->assertSee('admin\/dashboard\/data', false)          // URL data di JavaScript (garis miring di-escape oleh @json)
            ->assertSee('id="sakelar-sembunyi"', false)
            ->assertViewHas('data', fn ($d) => $d['suaraSah'] === 3 && $d['tersembunyi'] === false);
    }

    // ---------- sakelar sembunyikan ----------

    public function test_sakelar_menyembunyikan_perolehan_dan_angkanya_tidak_dikirim_sama_sekali(): void
    {
        $this->isiData();
        $admin = $this->actingAs($this->admin());

        $res = $admin->postJson('/admin/dashboard/sembunyikan', ['sembunyikan' => true])->assertOk();

        $this->assertTrue($res->json('tersembunyi'));
        foreach ($res->json('kandidat') as $k) {
            $this->assertArrayNotHasKey('total_suara', $k);
            $this->assertArrayNotHasKey('persen', $k);
        }
        $this->assertSame(['SATU', 'DUA', 'TIGA'], collect($res->json('kandidat'))->pluck('nama')->all());

        // angka partisipasi tetap tampil
        $this->assertSame(5, $res->json('totalDpt'));
        $this->assertSame(2, $res->json('sudahMilih'));

        // pilihan tersimpan di server: berlaku di permintaan berikutnya dan di tampilan awal halaman
        $this->getJson('/admin/dashboard/data')->assertJson(['tersembunyi' => true]);
        $halaman = $this->get('/admin/dashboard')->assertOk();
        $halaman->assertViewHas('data', fn ($d) => $d['tersembunyi'] === true && !array_key_exists('total_suara', $d['kandidat'][0]));
        // data awal yang tertanam di HTML (const AWAL) juga tidak boleh memuat angka per kandidat
        preg_match('/const AWAL\s*=\s*(.*?);\s+\/\//', $halaman->getContent(), $m);   // sampai komentar penutup baris
        $this->assertNotEmpty($m[1] ?? '');
        $this->assertStringNotContainsString('total_suara', $m[1]);
        $this->assertStringNotContainsString('persen', $m[1]);

        // dimatikan lagi -> angka kembali
        $kembali = $this->postJson('/admin/dashboard/sembunyikan', ['sembunyikan' => false])->assertOk();
        $this->assertFalse($kembali->json('tersembunyi'));
        $this->assertSame(2, collect($kembali->json('kandidat'))->firstWhere('id', 1)['total_suara']);
    }

    public function test_sakelar_berlaku_untuk_admin_lain(): void
    {
        $this->isiData();

        $this->actingAs($this->admin())->postJson('/admin/dashboard/sembunyikan', ['sembunyikan' => true])->assertOk();

        $this->actingAs($this->admin())->getJson('/admin/dashboard/data')->assertJson(['tersembunyi' => true]);
    }

    public function test_sakelar_menolak_input_tidak_valid_dengan_json(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/admin/dashboard/sembunyikan', [])
            ->assertStatus(422)->assertJsonValidationErrors('sembunyikan');

        $this->postJson('/admin/dashboard/sembunyikan', ['sembunyikan' => 'mungkin'])->assertStatus(422);
    }

    public function test_dashboard_tetap_jalan_walau_tabel_pengaturan_belum_dimigrasi(): void
    {
        $this->isiData();
        Schema::drop('pengaturan');
        Pengaturan::lupakanCache();

        $this->actingAs($this->admin())->getJson('/admin/dashboard/data')
            ->assertOk()->assertJson(['tersembunyi' => false, 'sudahMilih' => 2]);

        Pengaturan::lupakanCache();
    }
}
