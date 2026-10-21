<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\KodeQr;

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

        return view('admin.pemilih', compact('pemilih', 'search', 'nextNoUrut', 'daftarTps'));
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
        ], [
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
            'no_tps'         => 1,
            'no_bilik'       => 1,
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
        ]);

        // Eksekusi update ke database
        \Illuminate\Support\Facades\DB::table('pemilih')->where('nik', $nik)->update([
            'id'             => $request->nik, // Update ID (karena ID kita = NIK)
            'nik'            => $request->nik,
            'no_urut_dpt'    => $request->no_urut_dpt,
            'nama'           => strtoupper($request->nama),
            'jenis_kelamin'  => $request->jenis_kelamin,
            'umur'           => $request->umur,
        ]);

 
       return back()->with('success', 'Data pemilih berhasil diperbarui!');
    }

}