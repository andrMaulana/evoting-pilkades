<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; // Ini ibarat kunci inggris buat ngutak-ngatik database
use Illuminate\Support\Facades\Validator;
use App\Support\FotoKandidat;
use App\Support\KodeQr;
use App\Support\Pengaturan;

class VotingController extends Controller
{
    // Halaman utama: kamera scanner + surat suara. Daftar kandidat diambil dari database.
    public function index()
    {
        $kandidat = DB::table('kandidat')
            ->orderBy('no_urut', 'asc')
            ->get()
            ->map(fn ($k) => [
                'id'   => $k->id,          // dipakai untuk menyimpan suara (kunci asli di database)
                'no'   => $k->no_urut,     // hanya untuk tampilan dan struk
                'nama' => $k->nama,
                'foto' => FotoKandidat::url($k->foto),
            ])
            ->values();

        $tpsKiosk = Pengaturan::tpsKiosk();   // null = semua TPS boleh

        return view('voting', compact('kandidat', 'tpsKiosk'));
    }

    // Fungsi 1: Mengecek apakah QR Code valid dan belum dipakai
    public function cekPemilih(Request $request)
    {
        // Isi QR = kode acak pribadi (kode_qr), bukan NIK. NIK sengaja TIDAK diterima di sini.
        $kode = KodeQr::bersihkan($request->input('qr_id'));

        // Bentuk kode salah -> langsung dianggap tidak terdaftar, tanpa menyentuh database
        $pemilih = KodeQr::valid($kode)
            ? DB::table('pemilih')->where('kode_qr', $kode)->first()
            : null;

        // Skenario A: Pemilih bodong / QR Code tidak ada di database
        if (!$pemilih) {
            return response()->json([
                'status' => 'error',
                'pesan' => 'QR Code tidak terdaftar atau palsu!'
            ], 404);
        }

        // Skenario B: Pemilih valid, TAPI udah pernah nyoblos
        if ($pemilih->status_memilih) {
            return response()->json([
                'status' => 'sudah_memilih',
                'pesan' => 'Maaf, Anda sudah memberikan suara!'
            ], 403);
        }

        // Skenario B2: Layar voting ini dikunci ke satu TPS, tapi pemilih terdaftar di TPS lain
        $tpsKiosk = Pengaturan::tpsKiosk();
        if ($tpsKiosk !== null && (int) $pemilih->no_tps !== $tpsKiosk) {
            return response()->json([
                'status'   => 'beda_tps',
                'pesan'    => 'Maaf, Anda terdaftar di TPS ' . str_pad((string) $pemilih->no_tps, 2, '0', STR_PAD_LEFT)
                              . ' (Bilik ' . $pemilih->no_bilik . '). Silakan menuju TPS tersebut.',
                'no_tps'   => (int) $pemilih->no_tps,
                'no_bilik' => (int) $pemilih->no_bilik,
            ], 403);
        }

        // Skenario C: Pemilih valid dan BELUM nyoblos (Boleh lanjut)
        return response()->json([
            'status' => 'ok',
            'data' => [
                'nama' => $pemilih->nama,
                'no_tps' => $pemilih->no_tps,
                'no_bilik' => $pemilih->no_bilik
            ]
        ], 200);
    }

    // Fungsi 2: Menyimpan hasil coblosan ke kotak suara
    public function simpanSuara(Request $request)
    {
        // Validasi manual (bukan $request->validate) supaya selalu membalas JSON,
        // karena fetch() dari halaman voting tidak mengirim header Accept: application/json.
        $validator = Validator::make($request->all(), [
            'qr_id'       => 'required|string|max:64',
            'kandidat_id' => 'nullable|integer|exists:kandidat,id', // kosong = suara tidak sah
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'pesan'  => 'Data suara tidak valid. Silakan ulangi dari awal.'
            ], 422);
        }

        $kode       = KodeQr::bersihkan($request->input('qr_id'));
        $kandidatId = $request->input('kandidat_id');
        $tpsKiosk   = Pengaturan::tpsKiosk();

        try {
            $hasil = DB::transaction(function () use ($kode, $kandidatId, $tpsKiosk) {

                // 0. Bentuk kode salah (termasuk NIK) -> tidak terdaftar
                if (!KodeQr::valid($kode)) {
                    return 'tidak_terdaftar';
                }

                // 1. Tandai sudah memilih HANYA JIKA statusnya masih "belum memilih".
                //    Ini satu perintah UPDATE yang atomik: kalau ada dua request bersamaan
                //    (klik ganda / sengaja ditembak berulang), hanya satu yang berhasil
                //    mengubah baris, sisanya mendapat 0 baris terubah.
                $query = DB::table('pemilih')
                    ->where('kode_qr', $kode)
                    ->where('status_memilih', false);

                // Kalau layar voting dikunci ke satu TPS, syarat ini ikut masuk ke UPDATE yang sama (tetap atomik)
                if ($tpsKiosk !== null) {
                    $query->where('no_tps', $tpsKiosk);
                }

                $diperbarui = $query->update(['status_memilih' => true]);   // updated_at sengaja tidak disentuh

                if ($diperbarui === 0) {
                    // Gagal: bedakan QR palsu, sudah pernah memilih, atau beda TPS
                    $p = DB::table('pemilih')->where('kode_qr', $kode)->first(['status_memilih']);

                    if (!$p) {
                        return 'tidak_terdaftar';
                    }
                    return $p->status_memilih ? 'sudah_memilih' : 'beda_tps';
                }

                // 2. Masukkan suara ke kotak suara (tanpa identitas pemilih)
                DB::table('suara')->insert([
                    'kandidat_id' => $kandidatId,
                    'created_at'  => now(),
                    'updated_at'  => now()
                ]);

                return 'sukses';
            });
        } catch (\Throwable $e) {
            // Detail error hanya ke log server, jangan dibocorkan ke layar pemilih.
            // Transaksi otomatis di-rollback, jadi status pemilih tidak berubah.
            report($e);
            return response()->json([
                'status' => 'error',
                'pesan'  => 'Terjadi gangguan pada server. Suara belum tersimpan, silakan coba lagi.'
            ], 500);
        }

        if ($hasil === 'sukses') {
            return response()->json(['status' => 'sukses'], 200);
        }

        if ($hasil === 'sudah_memilih') {
            return response()->json([
                'status' => 'sudah_memilih',
                'pesan'  => 'Maaf, Anda sudah memberikan suara!'
            ], 403);
        }

        if ($hasil === 'beda_tps') {
            return response()->json([
                'status' => 'beda_tps',
                'pesan'  => 'Maaf, Anda terdaftar di TPS lain. Silakan menuju TPS Anda.'
            ], 403);
        }

        return response()->json([
            'status' => 'error',
            'pesan'  => 'QR Code tidak terdaftar atau palsu!'
        ], 404);
    }
}
