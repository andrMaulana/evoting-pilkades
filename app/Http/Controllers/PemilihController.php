<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\KodeQr;
use App\Support\Pengaturan;
use Illuminate\Support\Facades\Validator;

class PemilihController extends Controller
{
    // Batas undangan sekali cetak massal (supaya halaman tidak terlalu berat)
    private const BATAS_CETAK_MASSAL = 200;

    public function index(Request $request)
    {
        $search = $request->search;
        
        // Ambil data pemilih, urutkan berdasarkan Nomor Urut DPT
        $query = DB::table('pemilih')->orderBy('no_urut_dpt', 'asc');

        // Kalau panitia ngetik sesuatu di kolom pencarian
        if ($search) {
            $query->where('nama', 'like', "%{$search}%")
            ->orWhere('nik', 'like', "%{$search}%"); // ID ini NIK/Kode QR
        }

        // Tampilkan 50 baris per halaman biar nggak berat
        $pemilih = $query->paginate(50);

        // [BARU] Cari nomor urut paling besar, lalu tambah 1
        $maxNoUrut = \Illuminate\Support\Facades\DB::table('pemilih')->max('no_urut_dpt');
        $nextNoUrut = $maxNoUrut ? $maxNoUrut + 1 : 1;

        // Jangan lupa tambahin 'nextNoUrut' di dalam compact()
        // Daftar TPS yang ada, untuk filter cetak massal
        $daftarTps = DB::table('pemilih')->select('no_tps')->distinct()->orderBy('no_tps')->pluck('no_tps');

        // Sebaran pemilih per TPS dan bilik (untuk memeriksa hasil pengaturan massal)
        $sebaran = DB::table('pemilih')
            ->select('no_tps', 'no_bilik', DB::raw('count(*) as jumlah'))
            ->groupBy('no_tps', 'no_bilik')
            ->orderBy('no_tps')->orderBy('no_bilik')
            ->get();

        $tpsKiosk = Pengaturan::tpsKiosk();

        return view('admin.pemilih', compact('pemilih', 'search', 'nextNoUrut', 'daftarTps', 'sebaran', 'tpsKiosk'));
    }


    public function import(Request $request)
    {
        // Pastikan ada file yang di-upload
        if (!$request->hasFile('file_csv')) {
            return back();
        }

        $file = $request->file('file_csv');
        
        // 1. DETEKSI PEMISAH OTOMATIS (Titik Koma vs Koma)
        // Biar script ini jalan di Excel versi bahasa apapun
        $content = file_get_contents($file->getPathname());
        $delimiter = strpos($content, ';') !== false ? ';' : ',';

        $handle = fopen($file->getPathname(), "r");
        $dataPemilih = [];
        $kodeDipakai = [];   // jaga kode tidak kembar di dalam satu file import

        while (($row = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
            
            // 2. Ambil NIK di index ke-8 (Kolom I)
            $nik_kotor = isset($row[8]) ? $row[8] : '';
            
            // Hapus spasi atau titik yang nggak sengaja keketik di Excel
            $nik = preg_replace('/[^0-9]/', '', $nik_kotor);

            // 3. FILTER UTAMA SUPER KETAT
            // NIK E-KTP itu wajib 16 digit. 
            // Kalau kurang dari 15 digit (contohnya kayak angka '9' tadi atau sel kosong), LANGSUNG LEWATI!
            // Ini otomatis membuang semua Kop Surat, Judul, dan warga yang NIK-nya belum diisi.
            if (strlen($nik) < 15) {
                continue;
            }

            // 4. Deteksi L / P yang kolomnya terpisah
            $jenisKelamin = null;
            if (isset($row[2]) && (strtoupper(trim($row[2])) == 'L' || strtoupper(trim($row[2])) == 'LK')) {
                $jenisKelamin = 'L';
            } elseif (isset($row[3]) && (strtoupper(trim($row[3])) == 'P' || strtoupper(trim($row[3])) == 'PR')) {
                $jenisKelamin = 'P';
            }

            // 5. Bersihkan data usia
            $usia = isset($row[4]) && is_numeric(trim($row[4])) ? (int) trim($row[4]) : null;
            
            // 6. Ambil No Urut DPT dengan aman
            $no_urut = isset($row[0]) && is_numeric(trim($row[0])) ? (int) trim($row[0]) : 0;

            // 7. Masukkan ke keranjang antrean
            $dataPemilih[] = [
                'id'             => $nik, // Kunci utama tabel = NIK (isi QR di kartu = kode_qr, bukan ini)
                'nik'            => $nik,
                'kode_qr'        => $this->kodeBaruUntukBatch($kodeDipakai), // isi QR (acak), bukan NIK
                'no_urut_dpt'    => $no_urut,
                'nama'           => isset($row[1]) ? trim($row[1]) : 'TANPA NAMA',
                'jenis_kelamin'  => $jenisKelamin,
                'umur'           => $usia,
                'no_tps'         => 1, // Default ke 1 biar gak 0
                'no_bilik'       => 1, // Default ke 1 biar gak 0
                'status_memilih' => 0
            ];
        }
        fclose($handle);

        // 8. Masukkan ke Database (insertOrIgnore mencegah duplikat/error kalau di-klik 2x)
        if (!empty($dataPemilih)) {
            foreach (array_chunk($dataPemilih, 500) as $chunk) {
                \Illuminate\Support\Facades\DB::table('pemilih')->insertOrIgnore($chunk);
            }
        }

        // Di dalam fungsi import() (paling bawah):
        return back()->with('success', 'Ribuan data DPT berhasil di-import!');
    }

    // Fungsi untuk menambah data pemilih secara manual
    public function store(Request $request)
    {
        // Validasi inputan (NIK wajib 16 digit dan belum pernah terdaftar)
        $request->validate([
            'nik'           => 'required|numeric|digits:16|unique:pemilih,id',
            'no_urut_dpt'   => 'required|numeric|unique:pemilih,no_urut_dpt',
            'nama'          => 'required|string|max:255',
            'jenis_kelamin' => 'required|in:L,P',
            'umur'          => 'required|numeric|min:17',
            'no_tps'        => 'nullable|integer|between:1,99',
            'no_bilik'      => 'nullable|integer|between:1,99',
        ], [
            'no_tps.between'   => 'Gagal: Nomor TPS harus antara 1 sampai 99!',
            'no_bilik.between' => 'Gagal: Nomor bilik harus antara 1 sampai 99!',
            'nik.unique' => 'Gagal: NIK ini sudah terdaftar di sistem!',
            'nik.digits' => 'Gagal: NIK wajib berjumlah 16 digit angka!',
            'no_urut_dpt.unique' => 'Gagal: Nomor Urut DPT ini sudah dipakai warga lain!',
            'umur.min' => 'Gagal: Usia harus minimal 17 tahun!'
        ]);

        // Masukkan ke database
        \Illuminate\Support\Facades\DB::table('pemilih')->insert([
            'id'             => $request->nik,
            'nik'            => $request->nik,
            'kode_qr'        => KodeQr::unikDiDb(),
            'no_urut_dpt'    => $request->no_urut_dpt,
            'nama'           => strtoupper($request->nama),
            'jenis_kelamin'  => $request->jenis_kelamin,
            'umur'           => $request->umur,
            'no_tps'         => (int) ($request->input('no_tps') ?: 1),
            'no_bilik'       => (int) ($request->input('no_bilik') ?: 1),
            'status_memilih' => 0
        ]);
        
        return back()->with('success', 'Data pemilih baru berhasil ditambahkan!');
    }

    // Fungsi untuk menampilkan halaman cetak undangan
    public function cetak($nik)
    {
        // Cari data warga berdasarkan NIK
        $pemilih = \Illuminate\Support\Facades\DB::table('pemilih')->where('nik', $nik)->first();
        
        // Kalau datanya nggak ketemu, tendang balik
        if (!$pemilih) {
            return back();
        }

        $pemilih = $this->pastikanKode($pemilih);

        return view('admin.cetak_undangan', compact('pemilih'));
    }

    // Cetak undangan banyak warga sekaligus (filter: TPS dan/atau rentang No. Urut DPT)
    public function cetakMassal(Request $request)
    {
        $request->validate([
            'tps'    => 'nullable|integer|min:1',
            'dari'   => 'nullable|integer|min:1',
            'sampai' => 'nullable|integer|min:1',
        ]);

        $query = DB::table('pemilih')->orderBy('no_urut_dpt', 'asc');

        if ($request->filled('tps'))    { $query->where('no_tps', $request->tps); }
        if ($request->filled('dari'))   { $query->where('no_urut_dpt', '>=', $request->dari); }
        if ($request->filled('sampai')) { $query->where('no_urut_dpt', '<=', $request->sampai); }

        $total = (clone $query)->count();
        $batas = self::BATAS_CETAK_MASSAL;
        $pemilih = $query->limit($batas)->get()->map(fn ($p) => $this->pastikanKode($p));

        return view('admin.cetak_undangan_massal', compact('pemilih', 'total', 'batas'));
    }

    // Terbitkan QR baru (kartu hilang/rusak). QR lama langsung tidak berlaku.
    public function qrBaru($nik)
    {
        $pemilih = DB::table('pemilih')->where('nik', $nik)->first();

        if (!$pemilih) {
            return back()->with('error', 'Data pemilih tidak ditemukan.');
        }

        // Yang sudah memilih tidak bisa memilih lagi, jadi tidak perlu QR baru
        if ($pemilih->status_memilih) {
            return back()->with('error', "{$pemilih->nama} sudah memberikan suara, QR baru tidak diperlukan.");
        }

        DB::table('pemilih')->where('id', $pemilih->id)->update(['kode_qr' => KodeQr::unikDiDb()]);

        return back()->with('success', 'QR baru dibuat, QR lama tidak berlaku. Klik "Cetak" untuk mencetak ulang undangan.');
    }

    // Atur TPS & bilik secara massal per rentang No. Urut DPT.
    // Contoh: 1-500 = TPS 1 bilik 1, 501-1000 = TPS 1 bilik 2.
    public function aturTps(Request $request)
    {
        $baris = collect($request->input('aturan', []))
            ->filter(fn ($a) => is_array($a) && collect($a)->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty())
            ->values();   // baris yang kosong semua diabaikan

        if ($baris->isEmpty()) {
            return back()->with('error', 'Isi minimal satu aturan (rentang No. Urut, TPS, dan bilik).');
        }
        if ($baris->count() > 50) {
            return back()->with('error', 'Maksimal 50 aturan sekali simpan.');
        }

        $aturan = [];
        foreach ($baris as $i => $a) {
            $no = $i + 1;
            $v = Validator::make($a, [
                'dari'   => 'required|integer|min:1',
                'sampai' => 'required|integer|min:1',
                'tps'    => 'required|integer|between:1,99',
                'bilik'  => 'required|integer|between:1,99',
            ]);

            if ($v->fails()) {
                return back()->with('error', "Aturan {$no}: semua kolom wajib diisi dengan angka (No. Urut mulai 1, TPS dan bilik 1-99).");
            }
            if ((int) $a['sampai'] < (int) $a['dari']) {
                return back()->with('error', "Aturan {$no}: \"Sampai\" tidak boleh lebih kecil dari \"Dari\".");
            }

            $aturan[] = ['no' => $no, 'dari' => (int) $a['dari'], 'sampai' => (int) $a['sampai'], 'tps' => (int) $a['tps'], 'bilik' => (int) $a['bilik']];
        }

        // Rentang tidak boleh saling tumpang tindih (supaya hasilnya tidak bergantung urutan)
        $urut = collect($aturan)->sortBy('dari')->values();
        for ($i = 1; $i < $urut->count(); $i++) {
            if ($urut[$i]['dari'] <= $urut[$i - 1]['sampai']) {
                return back()->with('error', "Aturan {$urut[$i - 1]['no']} dan {$urut[$i]['no']} tumpang tindih. Perbaiki rentangnya.");
            }
        }

        $ringkas = [];
        $total = 0;

        DB::transaction(function () use ($aturan, &$ringkas, &$total) {
            foreach ($aturan as $r) {
                $n = DB::table('pemilih')
                    ->whereBetween('no_urut_dpt', [$r['dari'], $r['sampai']])
                    ->update(['no_tps' => $r['tps'], 'no_bilik' => $r['bilik']]);

                $total += $n;
                $ringkas[] = "TPS {$r['tps']} bilik {$r['bilik']}: {$n}";
            }
        });

        if ($total === 0) {
            return back()->with('error', 'Tidak ada pemilih pada rentang No. Urut itu, jadi tidak ada yang diubah.');
        }

        return back()->with('success', "{$total} pemilih diatur (" . implode('; ', $ringkas) . ').');
    }

    // Kunci layar voting ke satu TPS (kosong = semua TPS boleh)
    public function kunciKiosk(Request $request)
    {
        $v = Validator::make($request->all(), ['tps' => 'nullable|integer|between:1,99']);

        if ($v->fails()) {
            return back()->with('error', 'Nomor TPS harus angka 1-99, atau kosongkan untuk membuka semua TPS.');
        }

        $tps = $request->filled('tps') ? (int) $request->tps : null;

        try {
            Pengaturan::setTpsKiosk($tps);
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Pengaturan gagal disimpan. Jalankan php artisan migrate, lalu coba lagi.');
        }

        return back()->with('success', $tps
            ? "Layar voting dikunci ke TPS {$tps}. Pemilih dari TPS lain akan ditolak."
            : 'Layar voting dibuka untuk semua TPS.');
    }

    // Jaga-jaga: pemilih yang belum punya kode (mis. ditambah lewat jalur lain) langsung dibuatkan
    private function pastikanKode(object $pemilih): object
    {
        if (!$pemilih->kode_qr) {
            $pemilih->kode_qr = KodeQr::unikDiDb();
            DB::table('pemilih')->where('id', $pemilih->id)->update(['kode_qr' => $pemilih->kode_qr]);
        }
        return $pemilih;
    }

    // Kode baru yang dijamin tidak kembar di dalam satu batch import
    private function kodeBaruUntukBatch(array &$dipakai): string
    {
        do {
            $kode = KodeQr::buat();
        } while (isset($dipakai[$kode]));
        $dipakai[$kode] = true;

        return $kode;
    }
    // Fungsi untuk memproses editan data
    public function update(Request $request, $nik)
    {
        // Validasi, pastikan NIK dan No Urut tidak dipakai orang lain (kecuali dirinya sendiri)
        $request->validate([
            'nik'           => 'required|numeric|digits:16|unique:pemilih,id,' . $nik . ',id',
            'no_urut_dpt'   => 'required|numeric|unique:pemilih,no_urut_dpt,' . $nik . ',id',
            'nama'          => 'required|string|max:255',
            'jenis_kelamin' => 'required|in:L,P',
            'umur'          => 'required|numeric|min:17',
            'no_tps'        => 'nullable|integer|between:1,99',
            'no_bilik'      => 'nullable|integer|between:1,99',
        ], [
            'no_tps.between'   => 'Gagal: Nomor TPS harus antara 1 sampai 99!',
            'no_bilik.between' => 'Gagal: Nomor bilik harus antara 1 sampai 99!',
        ]);

        $data = [
            'id'             => $request->nik, // Update ID (karena ID kita = NIK)
            'nik'            => $request->nik,
            'no_urut_dpt'    => $request->no_urut_dpt,
            'nama'           => strtoupper($request->nama),
            'jenis_kelamin'  => $request->jenis_kelamin,
            'umur'           => $request->umur,
        ];

        // TPS/bilik hanya diubah kalau diisi (form lama tanpa kolom ini tidak mereset ke 1)
        if ($request->filled('no_tps'))   { $data['no_tps']   = (int) $request->no_tps; }
        if ($request->filled('no_bilik')) { $data['no_bilik'] = (int) $request->no_bilik; }

        // Eksekusi update ke database
        \Illuminate\Support\Facades\DB::table('pemilih')->where('nik', $nik)->update($data);

 
       return back()->with('success', 'Data pemilih berhasil diperbarui!');
    }

}