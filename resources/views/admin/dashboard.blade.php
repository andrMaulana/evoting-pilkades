@extends('layouts.admin')

@section('content')
    <style>
        /* Sorotan singkat saat angka berubah */
        @keyframes kedip { from { background-color: #fef08a; } to { background-color: transparent; } }
        .kedip { animation: kedip 1.2s ease-out; border-radius: 4px; }
        @media (prefers-reduced-motion: reduce) { .kedip { animation: none; } }
    </style>

    <div class="flex flex-wrap justify-between items-start gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Dashboard Real-Time</h1>
            <p class="text-slate-500 text-sm mt-1">Pantau perolehan suara dan partisipasi warga secara langsung.</p>
            <p class="mt-2 flex items-center gap-2 text-xs text-slate-500" role="status" aria-live="polite">
                <span id="live-dot" class="inline-block w-2.5 h-2.5 rounded-full bg-slate-300"></span>
                <span id="live-teks">Menghubungkan…</span>
                <span class="text-slate-300">·</span>
                <span>Diperbarui <span id="live-waktu" class="font-mono">{{ $data['diperbarui'] }}</span></span>
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-4">
            <!-- Sakelar: sembunyikan perolehan per kandidat (berlaku untuk semua admin) -->
            <label class="inline-flex items-center cursor-pointer select-none">
                <input type="checkbox" id="sakelar-sembunyi" class="sr-only peer" role="switch" @checked($data['tersembunyi'])>
                <span class="relative w-11 h-6 bg-slate-300 rounded-full transition-colors peer-checked:bg-[#1b5b9c] peer-focus-visible:ring-2 peer-focus-visible:ring-offset-2 peer-focus-visible:ring-[#1b5b9c] after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-5"></span>
                <span class="ml-3 text-sm font-medium text-slate-700">Sembunyikan perolehan kandidat</span>
            </label>
            <button id="btn-segarkan" type="button" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-lg transition-colors flex items-center gap-2 text-sm">
                🔄 Segarkan Sekarang
            </button>
        </div>
    </div>

    <!-- Pesan koneksi / sesi (muncul hanya kalau ada masalah) -->
    <div id="banner" role="alert" class="hidden"></div>

    <!-- Baris 1: Kartu Metrik Utama -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Card DPT -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm border-l-4 border-l-blue-500">
            <p class="text-slate-500 text-sm font-medium">Total DPT</p>
            <h3 class="text-3xl font-bold text-slate-800 mt-2"><span data-stat="totalDpt">{{ $data['totalDpt'] }}</span> <span class="text-base font-normal text-slate-500">Jiwa</span></h3>
        </div>
        <!-- Card Sudah Memilih -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm border-l-4 border-l-emerald-500">
            <p class="text-slate-500 text-sm font-medium">Sudah Memilih</p>
            <h3 class="text-3xl font-bold text-slate-800 mt-2"><span data-stat="sudahMilih">{{ $data['sudahMilih'] }}</span> <span class="text-base font-normal text-slate-500">Suara</span></h3>
        </div>
        <!-- Card Belum Memilih -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm border-l-4 border-l-amber-500">
            <p class="text-slate-500 text-sm font-medium">Belum Memilih</p>
            <h3 class="text-3xl font-bold text-slate-800 mt-2"><span data-stat="belumMilih">{{ $data['belumMilih'] }}</span> <span class="text-base font-normal text-slate-500">Pemilih</span></h3>
        </div>
        <!-- Card Status Suara -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm border-l-4 border-l-purple-500">
            <p class="text-slate-500 text-sm font-medium">Suara Sah / Tidak Sah</p>
            <h3 class="text-xl font-bold text-slate-800 mt-2">
                <span class="text-emerald-600"><span data-stat="suaraSah">{{ $data['suaraSah'] }}</span> Sah</span> <span class="text-slate-300 mx-2">|</span> <span class="text-red-500"><span data-stat="suaraTidakSah">{{ $data['suaraTidakSah'] }}</span> Batal</span>
            </h3>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Kolom Kiri: Quick Count Kandidat (Porsi 2/3) -->
        <div class="lg:col-span-2 bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
            <h2 class="text-lg font-bold text-slate-800 mb-6">Perolehan Suara Kandidat</h2>

            <p id="catatan-sembunyi" class="hidden mb-6 p-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm">
                Perolehan kandidat sedang disembunyikan. Matikan sakelar di atas untuk menampilkannya kembali.
            </p>

            <!-- Diisi oleh JavaScript dari data live -->
            <div id="daftar-kandidat" class="space-y-6"></div>
        </div>

        <!-- Kolom Kanan: Demografi Pemilih (Porsi 1/3) -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
            <h2 class="text-lg font-bold text-slate-800 mb-6">Demografi Partisipasi (Gender)</h2>

            <div class="space-y-6 mt-4">
                <!-- Bar Laki-laki -->
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center text-blue-600 font-bold text-xl">L</div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-slate-600 mb-1">Laki-Laki</p>
                        <h3 class="text-2xl font-bold"><span data-stat="pemilihLaki">{{ $data['pemilihLaki'] }}</span> <span class="text-sm font-normal text-slate-500">Jiwa</span></h3>
                    </div>
                </div>
                <!-- Bar Perempuan -->
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-pink-50 flex items-center justify-center text-pink-600 font-bold text-xl">P</div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-slate-600 mb-1">Perempuan</p>
                        <h3 class="text-2xl font-bold"><span data-stat="pemilihPerempuan">{{ $data['pemilihPerempuan'] }}</span> <span class="text-sm font-normal text-slate-500">Jiwa</span></h3>
                    </div>
                </div>

                <div class="mt-8 p-4 bg-slate-50 rounded-xl border border-slate-100">
                    <p class="text-xs text-slate-500 leading-relaxed">
                        * Data demografi di atas hanya menghitung warga yang <b>sudah menggunakan hak suaranya</b> di TPS.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function () {
        'use strict';

        // ---------- Pengaturan ----------
        const SELANG_MS    = {{ (int) $selangMs }};   // selang polling (ubah di AdminController::SELANG_POLLING_MS)
        const JEDA_MAKS_MS = 15000;                   // jeda terpanjang saat koneksi bermasalah
        const URL_DATA     = @json(route('admin.dashboard.data'));
        const URL_SEMBUNYI = @json(route('admin.dashboard.sembunyikan'));
        const URL_LOGIN    = @json(url('/login'));
        const CSRF         = @json(csrf_token());
        const AWAL         = @json($data);            // data tampilan awal (sudah segar dari server)

        const $ = (id) => document.getElementById(id);
        const el = {
            dot: $('live-dot'), teks: $('live-teks'), waktu: $('live-waktu'), banner: $('banner'),
            daftar: $('daftar-kandidat'), catatan: $('catatan-sembunyi'),
            sakelar: $('sakelar-sembunyi'), tombol: $('btn-segarkan')
        };

        let timer = null;
        let sedangAmbil = false;
        let sedangSakelar = false;
        let berhenti = false;     // true kalau sesi berakhir: polling dihentikan
        let gagal = 0;            // jumlah gagal berturut-turut (untuk jeda bertahap)
        let versi = 0;            // naik saat sakelar berubah, supaya data lama dari polling tidak menimpa
        let tanda = '';           // "sidik jari" daftar kandidat, untuk tahu kapan daftar harus dibangun ulang

        const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const valid = (d) => !!d && typeof d === 'object' && Number.isFinite(d.totalDpt) && Array.isArray(d.kandidat);

        function kedip(n) {
            n.classList.remove('kedip');
            void n.offsetWidth;
            n.classList.add('kedip');
        }

        function setStat(nama, nilai) {
            document.querySelectorAll('[data-stat="' + nama + '"]').forEach((n) => {
                const baru = String(nilai);
                if (n.textContent !== baru) {
                    n.textContent = baru;
                    kedip(n);
                }
            });
        }

        function status(kondisi, teks) {
            const warna = { ok: 'bg-emerald-500 animate-pulse', coba: 'bg-amber-500', putus: 'bg-red-500', jeda: 'bg-slate-300' };
            el.dot.className = 'inline-block w-2.5 h-2.5 rounded-full ' + (warna[kondisi] || warna.jeda);
            el.teks.textContent = teks;
        }

        function banner(pesan, jenis, tautan) {
            if (!pesan) {
                el.banner.className = 'hidden';
                el.banner.textContent = '';
                return;
            }
            el.banner.className = 'mb-6 p-4 rounded-xl border text-sm font-medium ' +
                (jenis === 'galat' ? 'bg-red-50 border-red-200 text-red-800' : 'bg-amber-50 border-amber-200 text-amber-800');
            el.banner.textContent = pesan;
            if (tautan) {
                const a = document.createElement('a');
                a.href = tautan.href;
                a.textContent = tautan.teks;
                a.className = 'underline font-bold ml-2';
                el.banner.appendChild(a);
            }
        }

        // ---------- Daftar kandidat ----------
        function isiBar(d) {
            d.kandidat.forEach((k) => {
                const baris = el.daftar.querySelector('[data-kid="' + k.id + '"]');
                if (!baris) return;
                const jumlah = baris.querySelector('[data-jumlah]');
                const persen = baris.querySelector('[data-persen]');
                const bar = baris.querySelector('[data-bar]');

                if (d.tersembunyi) {
                    jumlah.textContent = '—';
                    persen.textContent = 'Disembunyikan';
                    bar.style.width = '0%';
                    return;
                }
                const j = String(k.total_suara);
                if (jumlah.textContent !== j) {
                    jumlah.textContent = j;
                    kedip(jumlah);
                }
                persen.textContent = 'Suara (' + k.persen + '%)';
                bar.style.width = k.persen + '%';
            });
        }

        function renderKandidat(d) {
            el.catatan.classList.toggle('hidden', !d.tersembunyi);

            const tandaBaru = (d.tersembunyi ? 'h' : 's') + '|' + d.kandidat.map((k) => k.id + ':' + k.no_urut + ':' + k.nama).join('|');
            if (tandaBaru === tanda) {            // daftar sama: cukup perbarui angka (bar bergerak halus)
                isiBar(d);
                return;
            }

            tanda = tandaBaru;
            el.daftar.innerHTML = d.kandidat.map((k) => `
                <div data-kid="${k.id}">
                    <div class="flex justify-between items-end mb-2">
                        <span class="font-semibold text-slate-700">No.${k.no_urut} - ${esc(k.nama)}</span>
                        <div class="text-right">
                            <span class="font-bold text-lg text-[#1b5b9c]" data-jumlah>0</span>
                            <span class="text-slate-500 text-sm ml-1" data-persen></span>
                        </div>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-4 overflow-hidden">
                        <div class="bg-[#1b5b9c] h-4 rounded-full transition-all duration-1000" data-bar style="width:0%"></div>
                    </div>
                </div>`).join('');
            requestAnimationFrame(() => isiBar(d));   // bar tumbuh dari 0 saat pertama tampil
        }

        function terapkan(d) {
            ['totalDpt', 'sudahMilih', 'belumMilih', 'pemilihLaki', 'pemilihPerempuan', 'suaraSah', 'suaraTidakSah']
                .forEach((k) => setStat(k, d[k]));
            renderKandidat(d);
            if (!sedangSakelar) el.sakelar.checked = !!d.tersembunyi;
            el.waktu.textContent = d.diperbarui;
        }

        // ---------- Polling ----------
        function jadwal(ms) {
            clearTimeout(timer);
            if (berhenti) return;
            if (document.hidden) {                 // tab tidak terlihat: hemat server, lanjut saat dibuka lagi
                status('jeda', 'Dijeda (tab tidak aktif)');
                return;
            }
            timer = setTimeout(ambil, ms);
        }

        function sesiBerakhir() {
            berhenti = true;
            clearTimeout(timer);
            status('putus', 'Sesi berakhir');
            banner('Sesi login berakhir, data tidak lagi diperbarui.', 'galat', { href: URL_LOGIN, teks: 'Login ulang' });
        }

        async function ambil() {
            if (sedangAmbil || berhenti) return;
            sedangAmbil = true;
            clearTimeout(timer);
            const versiAwal = versi;
            let jeda = SELANG_MS;

            try {
                const res = await fetch(URL_DATA, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    cache: 'no-store'
                });
                if (res.status === 401 || res.status === 419) { sesiBerakhir(); return; }
                if (!res.ok) throw new Error('HTTP ' + res.status);

                const d = await res.json();
                if (!valid(d)) throw new Error('format data tidak dikenal');

                gagal = 0;
                if (versiAwal === versi) terapkan(d);      // abaikan data lama kalau sakelar baru saja diubah
                banner(null);
                status('ok', 'Live · tiap ' + (SELANG_MS / 1000) + ' detik');
            } catch (e) {
                gagal++;
                jeda = Math.min(SELANG_MS * Math.pow(2, gagal), JEDA_MAKS_MS);
                status('putus', 'Koneksi bermasalah, mencoba lagi…');
                banner('Tidak bisa mengambil data terbaru, angka di layar mungkin tertinggal. Mencoba lagi otomatis.', 'peringatan');
            } finally {
                sedangAmbil = false;
                jadwal(jeda);
            }
        }

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                clearTimeout(timer);
                if (!berhenti) status('jeda', 'Dijeda (tab tidak aktif)');
            } else {
                ambil();                                   // segarkan langsung begitu tab dibuka lagi
            }
        });

        el.tombol.addEventListener('click', () => {
            if (berhenti) { window.location.reload(); return; }
            ambil();
        });

        // ---------- Sakelar sembunyikan ----------
        el.sakelar.addEventListener('change', async () => {
            const nilai = el.sakelar.checked;
            sedangSakelar = true;
            el.sakelar.disabled = true;
            try {
                const res = await fetch(URL_SEMBUNYI, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json', 'Accept': 'application/json',
                        'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ sembunyikan: nilai })
                });
                if (res.status === 401 || res.status === 419) { sesiBerakhir(); throw new Error('sesi'); }
                if (!res.ok) throw new Error('HTTP ' + res.status);

                const d = await res.json();
                if (!valid(d)) throw new Error('format data tidak dikenal');

                versi++;
                sedangSakelar = false;
                terapkan(d);
                banner(null);
            } catch (e) {
                el.sakelar.checked = !nilai;                // kembalikan ke keadaan sebelumnya
                if (e.message !== 'sesi') {
                    banner('Gagal mengubah pengaturan. Coba lagi. Kalau baru memperbarui aplikasi, jalankan php artisan migrate.', 'galat');
                }
            } finally {
                sedangSakelar = false;
                el.sakelar.disabled = false;
            }
        });

        // ---------- Mulai ----------
        terapkan(AWAL);
        status('coba', 'Menghubungkan…');
        jadwal(SELANG_MS);
    })();
    </script>
@endsection
