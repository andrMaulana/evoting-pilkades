@extends('layouts.admin')

@section('content')

    <!-- Toast Notifikasi Sukses -->
    @if(session('success'))
    <div id="toast-success" class="fixed top-5 right-5 z-50 flex items-center w-full max-w-xs p-4 text-slate-600 bg-white rounded-xl shadow-lg border border-emerald-100 transition-all duration-500 transform translate-x-0" role="alert">
        <div class="inline-flex items-center justify-center flex-shrink-0 w-10 h-10 text-emerald-500 bg-emerald-50 rounded-lg">
            <svg class="w-6 h-6" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5Zm3.707 8.207-4 4a1 1 0 0 1-1.414 0l-2-2a1 1 0 0 1 1.414-1.414L9 10.586l3.293-3.293a1 1 0 0 1 1.414 1.414Z"/>
            </svg>
        </div>
        <div class="ml-3 text-sm font-semibold">{{ session('success') }}</div>
        <button type="button" onclick="document.getElementById('toast-success').remove()" class="ml-auto -mx-1.5 -my-1.5 bg-white text-slate-400 hover:text-red-500 rounded-lg focus:ring-2 focus:ring-slate-300 p-1.5 hover:bg-slate-50 inline-flex items-center justify-center h-8 w-8">
            <span class="sr-only">Tutup</span>
            ✕
        </button>
    </div>

    <!-- Script buat ngilangin toast otomatis setelah 3 detik -->
    <script>
        setTimeout(() => {
            let toast = document.getElementById('toast-success');
            if(toast) {
                toast.classList.add('opacity-0', 'translate-x-full'); // Animasi geser & pudar
                setTimeout(() => toast.remove(), 500); // Hapus elemen dari HTML
            }
        }, 3000);
    </script>
    @endif

    @if(session('error'))
    <div id="toast-error" class="fixed top-5 right-5 z-50 flex items-center w-full max-w-sm p-4 text-slate-600 bg-white rounded-xl shadow-lg border border-red-200" role="alert">
        <div class="inline-flex items-center justify-center flex-shrink-0 w-10 h-10 text-red-500 bg-red-50 rounded-lg font-bold">!</div>
        <div class="ml-3 text-sm font-semibold">{{ session('error') }}</div>
        <button type="button" onclick="document.getElementById('toast-error').remove()" class="ml-auto -mx-1.5 -my-1.5 bg-white text-slate-400 hover:text-red-500 rounded-lg p-1.5 hover:bg-slate-50 inline-flex items-center justify-center h-8 w-8">
            <span class="sr-only">Tutup</span>
            ✕
        </button>
    </div>
    @endif

    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Daftar Pemilih Tetap (DPT)</h1>
            <p class="text-slate-500 text-sm mt-1">Kelola data warga, import dari Excel, dan cetak undangan.</p>
        </div>
        
        <!-- INI BAGIAN TAHAP 3 YANG UDAH DIGABUNGIN -->
        <form action="{{ route('admin.pemilih.import') }}" method="POST" enctype="multipart/form-data" class="flex gap-3">
            @csrf
            <!-- Input file tersembunyi -->
            <input type="file" name="file_csv" accept=".csv" id="fileCsv" class="hidden" onchange="this.form.submit()">
            
            <!-- Tombol Import yang memicu input file -->
            <button type="button" onclick="document.getElementById('fileCsv').click()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-lg transition-colors flex items-center gap-2 text-sm">
                📊 Import CSV
            </button>
            
            <!-- Tombol Cetak Massal -->
            <button type="button" onclick="document.getElementById('modalCetakMassal').classList.remove('hidden')" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white font-semibold rounded-lg transition-colors flex items-center gap-2 text-sm">
                🖨️ Cetak Massal
            </button>

            <!-- Tombol Atur TPS & Bilik massal -->
            <button type="button" onclick="document.getElementById('modalAturTps').classList.remove('hidden')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-lg transition-colors flex items-center gap-2 text-sm">
                📍 Atur TPS &amp; Bilik
            </button>

            <!-- Tombol Kunci layar voting ke satu TPS -->
            <button type="button" onclick="document.getElementById('modalKunci').classList.remove('hidden')" class="px-4 py-2 font-semibold rounded-lg transition-colors flex items-center gap-2 text-sm {{ $tpsKiosk ? 'bg-amber-100 hover:bg-amber-200 text-amber-800' : 'bg-slate-100 hover:bg-slate-200 text-slate-700' }}">
                🔒 Kiosk: {{ $tpsKiosk ? 'TPS ' . str_pad((string) $tpsKiosk, 2, '0', STR_PAD_LEFT) : 'Semua TPS' }}
            </button>

            <!-- Tombol Tambah Manual (Belum aktif, nanti kita buat formnya) -->
            <button type="button" onclick="document.getElementById('modalTambah').classList.remove('hidden')" class="px-4 py-2 bg-[#1b5b9c] hover:bg-[#154a80] text-white font-semibold rounded-lg transition-colors flex items-center gap-2 text-sm">
                + Tambah Manual
            </button>
        </form>
    </div>

    <!-- Kotak Pencarian -->
    <div class="bg-white p-4 rounded-t-2xl border border-slate-200 shadow-sm border-b-0">
        <form action="{{ route('admin.pemilih') }}" method="GET" class="flex gap-4 max-w-md">
            <input type="text" name="search" value="{{ $search }}" placeholder="Cari nama atau NIK..." 
                   class="w-full px-4 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-[#1b5b9c] focus:border-[#1b5b9c] outline-none">
            <button type="submit" class="px-5 py-2 bg-slate-800 hover:bg-slate-900 text-white font-semibold rounded-lg">Cari</button>
            @if($search)
                <a href="{{ route('admin.pemilih') }}" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold rounded-lg text-sm flex items-center">Reset</a>
            @endif
        </form>
    </div>

    <!-- Tabel Data -->
    <div class="bg-white border border-slate-200 shadow-sm rounded-b-2xl overflow-hidden">
        <table class="w-full text-left text-sm text-slate-600">
            <thead class="bg-slate-50 border-b border-slate-200 text-slate-700">
                <tr>
                    <th class="px-6 py-4 font-bold">No. Urut</th>
                    <th class="px-6 py-4 font-bold">NIK</th>
                    <th class="px-6 py-4 font-bold">Nama Lengkap</th>
                    <th class="px-6 py-4 font-bold">L/P</th>
                    <th class="px-6 py-4 font-bold">Usia</th>
                    <th class="px-6 py-4 font-bold">TPS / Bilik</th>
                    <th class="px-6 py-4 font-bold text-center">Status</th>
                    <th class="px-6 py-4 font-bold text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($pemilih as $p)
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-6 py-4">{{ $p->no_urut_dpt }}</td>
                    <td class="px-6 py-4 font-mono text-xs">{{ $p->nik }}</td>
                    <td class="px-6 py-4 font-semibold text-slate-800">{{ $p->nama }}</td>
                    <td class="px-6 py-4">{{ $p->jenis_kelamin ?? '-' }}</td>
                    <td class="px-6 py-4">{{ $p->umur ? $p->umur . ' thn' : '-' }}</td>
                    <td class="px-6 py-4 whitespace-nowrap">{{ $p->no_tps }} / {{ $p->no_bilik }}</td>
                    <td class="px-6 py-4 text-center">
                        @if($p->status_memilih == 1)
                            <span class="inline-block px-3 py-1 bg-emerald-100 text-emerald-700 rounded-full text-xs font-bold">Sudah</span>
                        @else
                            <span class="inline-block px-3 py-1 bg-amber-100 text-amber-700 rounded-full text-xs font-bold">Belum</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-right">
<!-- Ganti tombol Edit jadi ini -->
                        <button onclick="bukaModalEdit({{ \Illuminate\Support\Js::from(['nik' => $p->nik, 'no_urut' => $p->no_urut_dpt, 'nama' => $p->nama, 'jk' => $p->jenis_kelamin, 'umur' => $p->umur, 'tps' => $p->no_tps, 'bilik' => $p->no_bilik]) }})" class="text-[#1b5b9c] hover:underline text-xs font-bold mr-3">Edit</button>
                        <a href="{{ route('admin.pemilih.cetak', $p->nik) }}" target="_blank" class="text-slate-500 hover:text-slate-800 text-xs font-bold">🖨️ Cetak</a>
                        @if($p->status_memilih != 1)
                        <form action="{{ route('admin.pemilih.qr-baru', $p->nik) }}" method="POST" class="inline" onsubmit="return confirm('Terbitkan QR baru untuk pemilih ini? QR lama langsung tidak berlaku dan kartunya harus dicetak ulang.')">
                            @csrf
                            <button type="submit" class="text-amber-600 hover:text-amber-800 text-xs font-bold ml-3">🔄 QR Baru</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-6 py-8 text-center text-slate-500">
                        Belum ada data pemilih. Silakan import dari Excel atau tambah manual.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Navigasi Halaman (Pagination) -->
    <div class="mt-6">
        {{ $pemilih->links('pagination::tailwind') }}
    </div>


    <!-- Modal Cetak Massal -->
    <div id="modalCetakMassal" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                <h3 class="font-bold text-slate-800">Cetak Undangan Massal</h3>
                <button type="button" onclick="document.getElementById('modalCetakMassal').classList.add('hidden')" class="text-slate-400 hover:text-red-500 font-bold">✕</button>
            </div>
            <form action="{{ route('admin.pemilih.cetak-massal') }}" method="GET" target="_blank" class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">TPS</label>
                    <select name="tps" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                        <option value="">Semua TPS</option>
                        @foreach($daftarTps as $tps)
                            <option value="{{ $tps }}">TPS {{ str_pad($tps, 2, '0', STR_PAD_LEFT) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Dari No. Urut</label>
                        <input type="number" name="dari" min="1" placeholder="1" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Sampai No. Urut</label>
                        <input type="number" name="sampai" min="1" placeholder="200" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    </div>
                </div>
                <p class="text-xs text-slate-500">Maksimal 200 undangan sekali cetak. Untuk DPT yang lebih banyak, cetak per rentang No. Urut (misalnya 1–200, lalu 201–400).</p>
                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="document.getElementById('modalCetakMassal').classList.add('hidden')" class="flex-1 px-4 py-2 bg-slate-100 text-slate-600 font-semibold rounded-lg text-sm">Batal</button>
                    <button type="submit" class="flex-1 px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white font-semibold rounded-lg text-sm">Buka Halaman Cetak</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Atur TPS & Bilik Massal -->
    <div id="modalAturTps" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[92vh] overflow-y-auto">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50 sticky top-0">
                <h3 class="font-bold text-slate-800">Atur TPS &amp; Bilik Massal</h3>
                <button type="button" onclick="document.getElementById('modalAturTps').classList.add('hidden')" class="text-slate-400 hover:text-red-500 font-bold">✕</button>
            </div>
            <form id="formAturTps" action="{{ route('admin.pemilih.atur-tps') }}" method="POST" class="p-6 space-y-5">
                @csrf

                <div>
                    <p class="text-sm font-semibold text-slate-700">Sebaran saat ini</p>
                    <div class="flex flex-wrap gap-2 mt-2">
                        @forelse($sebaran as $s)
                            <span class="px-2.5 py-1 rounded-full bg-slate-100 text-xs font-medium text-slate-700">TPS {{ $s->no_tps }} · Bilik {{ $s->no_bilik }}: <b>{{ $s->jumlah }}</b></span>
                        @empty
                            <span class="text-xs text-slate-500">Belum ada pemilih.</span>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm font-semibold text-slate-700">Isi otomatis (bagi rata ke beberapa bilik)</p>
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mt-3 items-end">
                        <div><label class="block text-xs font-semibold text-slate-600 mb-1">Dari No. Urut</label><input type="number" id="ba_dari" min="1" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"></div>
                        <div><label class="block text-xs font-semibold text-slate-600 mb-1">Sampai No. Urut</label><input type="number" id="ba_sampai" min="1" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"></div>
                        <div><label class="block text-xs font-semibold text-slate-600 mb-1">TPS</label><input type="number" id="ba_tps" min="1" max="99" value="1" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"></div>
                        <div><label class="block text-xs font-semibold text-slate-600 mb-1">Jumlah bilik</label><input type="number" id="ba_bilik" min="1" max="20" value="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"></div>
                        <button type="button" id="btnBuatAturan" class="px-3 py-2 bg-slate-800 hover:bg-slate-900 text-white font-semibold rounded-lg text-sm">Buat aturan</button>
                    </div>
                    <p class="text-xs text-slate-500 mt-2">Contoh: 1 sampai 1000, TPS 1, 2 bilik, hasilnya bilik 1 = 1–500 dan bilik 2 = 501–1000.</p>
                    <p id="bantuError" class="text-xs text-red-600 mt-1 hidden"></p>
                </div>

                <div>
                    <p class="text-sm font-semibold text-slate-700 mb-2">Aturan yang akan diterapkan</p>
                    <div id="aturanBaris" class="space-y-2"></div>
                    <button type="button" id="btnTambahAturan" class="mt-3 text-sm font-semibold text-[#1b5b9c] hover:underline">+ Tambah aturan manual</button>
                </div>

                <p class="text-xs text-slate-500">Hanya TPS dan bilik yang berubah, pemilih di luar rentang tidak disentuh. Rentang antar-aturan tidak boleh tumpang tindih.</p>

                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="document.getElementById('modalAturTps').classList.add('hidden')" class="flex-1 px-4 py-2 bg-slate-100 text-slate-600 font-semibold rounded-lg text-sm">Batal</button>
                    <button type="submit" class="flex-1 px-4 py-2 bg-[#1b5b9c] hover:bg-[#154a80] text-white font-semibold rounded-lg text-sm">Terapkan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Kunci layar voting ke satu TPS -->
    <div id="modalKunci" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                <h3 class="font-bold text-slate-800">Kunci Layar Voting ke TPS</h3>
                <button type="button" onclick="document.getElementById('modalKunci').classList.add('hidden')" class="text-slate-400 hover:text-red-500 font-bold">✕</button>
            </div>
            <form action="{{ route('admin.pemilih.kunci-kiosk') }}" method="POST" class="p-6 space-y-4">
                @csrf
                <p class="text-sm text-slate-600">
                    Saat ini layar voting
                    <b>{{ $tpsKiosk ? 'dikunci ke TPS ' . str_pad((string) $tpsKiosk, 2, '0', STR_PAD_LEFT) : 'melayani semua TPS' }}</b>.
                </p>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Kunci ke TPS nomor</label>
                    <input type="number" name="tps" min="1" max="99" value="{{ $tpsKiosk }}" placeholder="Kosongkan = semua TPS" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                </div>
                <p class="text-xs text-slate-500">Pemilih yang terdaftar di TPS lain akan ditolak dan diberi tahu TPS serta bilik mereka. Pengaturan ini berlaku untuk layar voting yang memakai server ini.</p>
                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="document.getElementById('modalKunci').classList.add('hidden')" class="flex-1 px-4 py-2 bg-slate-100 text-slate-600 font-semibold rounded-lg text-sm">Batal</button>
                    <button type="submit" class="flex-1 px-4 py-2 bg-[#1b5b9c] hover:bg-[#154a80] text-white font-semibold rounded-lg text-sm">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    (function () {
        const wadah = document.getElementById('aturanBaris');
        const form = document.getElementById('formAturTps');
        const err = document.getElementById('bantuError');
        let urutan = 0;

        const nilai = (id) => parseInt(document.getElementById(id).value, 10);
        const kolom = (label, nama, v, i, extra) =>
            '<div><label class="block text-xs font-semibold text-slate-600 mb-1">' + label + '</label>' +
            '<input type="number" min="1" ' + (extra || '') + ' name="aturan[' + i + '][' + nama + ']" value="' + (v === undefined || v === null ? '' : v) + '" ' +
            'class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"></div>';

        function tambahBaris(v) {
            v = v || {};
            const i = urutan++;
            const div = document.createElement('div');
            div.setAttribute('data-baris', '');
            div.className = 'grid grid-cols-2 sm:grid-cols-[1fr_1fr_1fr_1fr_auto] gap-2 items-end';
            div.innerHTML =
                kolom('Dari No. Urut', 'dari', v.dari, i) + kolom('Sampai No. Urut', 'sampai', v.sampai, i) +
                kolom('TPS', 'tps', v.tps, i, 'max="99"') + kolom('Bilik', 'bilik', v.bilik, i, 'max="99"') +
                '<button type="button" data-hapus aria-label="Hapus aturan" class="px-3 py-2 text-red-500 hover:bg-red-50 rounded-lg text-sm">✕</button>';
            wadah.appendChild(div);
        }

        // Bagi rentang ke n bilik dengan ukuran sama (bilik terakhir memakai sisa)
        function bagiRata(dari, sampai, tps, n) {
            const ukuran = Math.ceil((sampai - dari + 1) / n);
            const hasil = [];
            for (let b = 1; b <= n; b++) {
                const a = dari + (b - 1) * ukuran;
                if (a > sampai) break;
                hasil.push({ dari: a, sampai: Math.min(sampai, a + ukuran - 1), tps: tps, bilik: b });
            }
            return hasil;
        }
        window.bagiRataBilik = bagiRata;

        function barisKosong(div) {
            return [...div.querySelectorAll('input')].every((x) => x.value === '');
        }

        document.getElementById('btnTambahAturan').addEventListener('click', () => tambahBaris());

        wadah.addEventListener('click', (e) => {
            const tombol = e.target.closest('[data-hapus]');
            if (tombol) tombol.closest('[data-baris]').remove();
        });

        document.getElementById('btnBuatAturan').addEventListener('click', () => {
            const dari = nilai('ba_dari'), sampai = nilai('ba_sampai'), tps = nilai('ba_tps'), n = nilai('ba_bilik');
            let masalah = '';
            if (!(dari >= 1) || !(sampai >= dari)) masalah = 'Isi rentang No. Urut dengan benar (Sampai tidak boleh lebih kecil dari Dari).';
            else if (!(tps >= 1 && tps <= 99)) masalah = 'TPS harus 1 sampai 99.';
            else if (!(n >= 1 && n <= 20)) masalah = 'Jumlah bilik harus 1 sampai 20.';

            err.textContent = masalah;
            err.classList.toggle('hidden', masalah === '');
            if (masalah) return;

            [...wadah.querySelectorAll('[data-baris]')].filter(barisKosong).forEach((d) => d.remove());
            bagiRata(dari, sampai, tps, n).forEach(tambahBaris);
        });

        form.addEventListener('submit', (e) => {
            const baris = [...wadah.querySelectorAll('[data-baris]')].filter((d) => !barisKosong(d));
            if (baris.length === 0) {
                e.preventDefault();
                err.textContent = 'Belum ada aturan. Pakai "Buat aturan" atau "Tambah aturan manual".';
                err.classList.remove('hidden');
                return;
            }
            if (!confirm('Terapkan ' + baris.length + ' aturan TPS/bilik? Data TPS dan bilik pemilih pada rentang itu akan diganti.')) {
                e.preventDefault();
            }
        });

        tambahBaris();   // satu baris kosong sebagai awal
    })();
    </script>

    <!-- Modal Tambah Manual -->
    <div id="modalTambah" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                <h3 class="font-bold text-slate-800">Tambah Pemilih Manual</h3>
                <button onclick="document.getElementById('modalTambah').classList.add('hidden')" class="text-slate-400 hover:text-red-500 font-bold">✕</button>
            </div>
            
            <form action="{{ route('admin.pemilih.store') }}" method="POST" class="p-6 space-y-4">
                @csrf
                <!-- Nampilin Pesan Error Validasi -->
                @if($errors->any())
                    <div class="p-3 bg-red-50 text-red-600 text-sm rounded-lg border border-red-100 mb-4">
                        {{ $errors->first() }}
                    </div>
                @endif

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Nomor Urut DPT</label>
                    <input type="number" name="no_urut_dpt" value="{{ $nextNoUrut }}" required class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-[#1b5b9c] outline-none">
                    <p class="text-xs text-slate-500 mt-1">*Otomatis melanjutkan nomor terakhir</p>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">NIK (16 Digit - Untuk QR Code)</label>
                    <input type="number" name="nik" required class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-[#1b5b9c] outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Nama Lengkap</label>
                    <input type="text" name="nama" required class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-[#1b5b9c] outline-none">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Jenis Kelamin</label>
                        <select name="jenis_kelamin" required class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-[#1b5b9c] outline-none">
                            <option value="L">Laki-laki</option>
                            <option value="P">Perempuan</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Usia</label>
                        <input type="number" name="umur" required class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-[#1b5b9c] outline-none">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">TPS</label>
                        <input type="number" name="no_tps" min="1" max="99" value="1" class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-[#1b5b9c] outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Bilik</label>
                        <input type="number" name="no_bilik" min="1" max="99" value="1" class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-[#1b5b9c] outline-none">
                    </div>
                </div>
                <div class="pt-4 flex gap-3">
                    <button type="button" onclick="document.getElementById('modalTambah').classList.add('hidden')" class="flex-1 px-4 py-2 bg-slate-100 text-slate-600 font-semibold rounded-lg hover:bg-slate-200">Batal</button>
                    <button type="submit" class="flex-1 px-4 py-2 bg-[#1b5b9c] text-white font-semibold rounded-lg hover:bg-[#154a80]">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Script buka modal otomatis kalau ada error validasi NIK -->
    @if($errors->any())
        <script> document.getElementById('modalTambah').classList.remove('hidden'); </script>
    @endif

<!-- Modal Edit Pemilih -->
    <div id="modalEdit" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                <h3 class="font-bold text-slate-800">Edit Data Pemilih</h3>
                <button onclick="document.getElementById('modalEdit').classList.add('hidden')" class="text-slate-400 hover:text-red-500 font-bold">✕</button>
            </div>
            
            <!-- Form Edit (Action URL akan diisi oleh JavaScript) -->
            <form id="formEdit" method="POST" class="p-6 space-y-4">
                @csrf
                @method('PUT')
                
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Nomor Urut DPT</label>
                    <input type="number" name="no_urut_dpt" id="edit_no_urut" required class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-[#1b5b9c] outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">NIK (16 Digit)</label>
                    <input type="number" name="nik" id="edit_nik" required class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-[#1b5b9c] outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Nama Lengkap</label>
                    <input type="text" name="nama" id="edit_nama" required class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-[#1b5b9c] outline-none">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Jenis Kelamin</label>
                        <select name="jenis_kelamin" id="edit_jk" required class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-[#1b5b9c] outline-none">
                            <option value="L">Laki-laki</option>
                            <option value="P">Perempuan</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Usia</label>
                        <input type="number" name="umur" id="edit_umur" required class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-[#1b5b9c] outline-none">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">TPS</label>
                        <input type="number" name="no_tps" id="edit_tps" min="1" max="99" class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-[#1b5b9c] outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Bilik</label>
                        <input type="number" name="no_bilik" id="edit_bilik" min="1" max="99" class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-[#1b5b9c] outline-none">
                    </div>
                </div>
                <div class="pt-4 flex gap-3">
                    <button type="button" onclick="document.getElementById('modalEdit').classList.add('hidden')" class="flex-1 px-4 py-2 bg-slate-100 text-slate-600 font-semibold rounded-lg hover:bg-slate-200">Batal</button>
                    <button type="submit" class="flex-1 px-4 py-2 bg-[#1b5b9c] text-white font-semibold rounded-lg hover:bg-[#154a80]">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Script Pelempar Data ke Modal -->
    <script>
        function bukaModalEdit(p) {
            // Isi form dengan data warga yang dipilih
            document.getElementById('edit_nik').value = p.nik;
            document.getElementById('edit_no_urut').value = p.no_urut;
            document.getElementById('edit_nama').value = p.nama;
            document.getElementById('edit_jk').value = p.jk;
            document.getElementById('edit_umur').value = p.umur;
            document.getElementById('edit_tps').value = p.tps;
            document.getElementById('edit_bilik').value = p.bilik;

            // Ubah tujuan URL action sesuai NIK warga
            document.getElementById('formEdit').action = '/admin/pemilih/update/' + encodeURIComponent(p.nik);

            // Tampilkan modalnya
            document.getElementById('modalEdit').classList.remove('hidden');
        }
    </script>
@endsection