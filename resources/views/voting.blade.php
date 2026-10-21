<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>E-Voting Pilkades Curug</title>


    <!-- Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Tailwind CSS (via CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: { DEFAULT: '#1b5b9c', dark: '#154a80', tint: '#eaf2fa' },
                        ink: '#0f1f33',
                        line: '#dbe3ec'
                    },
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif']
                    }
                }
            }
        };
    </script>

    <!-- Library html5-qrcode untuk scanner (versi dikunci agar perilaku stabil) -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

    <!-- Pembuat QR struk + pembuat PDF struk (versi dikunci) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <style>
        /* ---------- Kartu kandidat ---------- */
        .candidate-card {
            transition: box-shadow .15s ease, transform .15s ease, background-color .15s ease;
        }
        .candidate-card:focus-visible {
            outline: 3px solid #1b5b9c;
            outline-offset: 3px;
        }
        .candidate-card[aria-pressed="true"] {
            background-color: #eaf2fa;
            box-shadow: 0 0 0 3px #1b5b9c, 0 14px 28px -14px rgba(27, 91, 156, .5);
            transform: translateY(-2px);
        }
        .candidate-card .check {
            opacity: 0;
            transform: scale(.6);
            transition: opacity .15s ease, transform .15s ease;
        }
        .candidate-card[aria-pressed="true"] .check {
            opacity: 1;
            transform: scale(1);
        }
        @media (hover: hover) {
            .candidate-card:hover { box-shadow: 0 10px 24px -14px rgba(15, 31, 51, .35); }
            .candidate-card[aria-pressed="true"]:hover {
                box-shadow: 0 0 0 3px #1b5b9c, 0 14px 28px -14px rgba(27, 91, 156, .5);
            }
        }

        /* ---------- Area kamera ----------
           Video dibiarkan memakai rasio aslinya (tanpa object-fit: cover)
           supaya area baca QR yang dihitung library sesuai dengan yang tampak di layar. */
        #reader { width: 100%; background: #0f1f33; }
        #reader video { display: block; width: 100% !important; height: auto !important; }

        /* ---------- Garis laser ----------
           Hanya animasi transform (jalan di GPU), tidak menyentuh proses decode QR.
           Ukuran kotak (--box) diisi lewat JS agar pas dengan area baca library. */
        @keyframes laser-sweep {
            0%, 100% { transform: translateY(0);                        opacity: 0; }
            10%, 90% { opacity: 1; }
            50%      { transform: translateY(calc(var(--box, 200px) - 2px)); }
        }
        .laser-line {
            position: absolute;
            left: 4%;
            top: 0;
            width: 92%;
            height: 2px;
            border-radius: 2px;
            background: #ef4444;
            box-shadow: 0 0 10px 2px rgba(239, 68, 68, .65);
            animation: laser-sweep 2.8s linear infinite;
            will-change: transform, opacity;
        }

        /* ---------- Struk (hanya tampil saat dicetak, kertas A4) ---------- */
        #struk-print { display: none; }
        @media print {
            @page { size: A4; margin: 0; }
            html, body { background: #fff !important; }
            body > *:not(#struk-print) { display: none !important; }
            #struk-print {
                display: flex !important;
                flex-direction: column;
                align-items: center;
                width: 210mm;
                height: 296mm;
                padding: 22mm 15mm 0;
                box-sizing: border-box;
                background: #fff;
                color: #000;
                font-family: Arial, Helvetica, sans-serif;
                font-weight: 700;
                text-align: center;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            #struk-print .sp-t1 { font-size: 28pt; line-height: 1.2; }
            #struk-print .sp-t2 { font-size: 19pt; line-height: 1.3; }
            #struk-print .sp-qr { width: 170mm; height: 170mm; margin-top: 2mm; }
            #struk-print .sp-code { margin-top: -8mm; font-size: 17pt; word-break: break-all; }
        }

        /* ---------- Hitung mundur auto-reset ---------- */
        @keyframes drain {
            from { width: 100%; }
            to   { width: 0%; }
        }

        @media (prefers-reduced-motion: reduce) {
            * { transition: none !important; animation-duration: 0.01ms !important; }
            .laser-line { display: none !important; }
        }
    </style>
</head>
<body class="min-h-[100dvh] flex flex-col bg-[#f6f8fb] text-ink font-sans antialiased">

    <!-- ================= HEADER ================= -->
    <header class="bg-white border-b border-line">
        <div class="max-w-6xl mx-auto px-4 sm:px-8 h-16 flex items-center gap-3">
            <img src="{{ asset('img/assets/logo_panitia_pilkades.png') }}" alt="Logo Panitia Pilkades"
                 class="h-11 w-auto" draggable="false" onerror="this.style.display='none'">
            <div class="leading-tight">
                <p class="font-bold text-brand">Pilkades Curug</p>
                <p class="text-xs text-slate-500">Pemilihan Kepala Desa</p>
            </div>
        </div>
    </header>

    <!-- ================= HALAMAN 1: SCAN QR CODE ================= -->
    <main id="page-scan" class="flex-1">
        <div class="max-w-3xl mx-auto px-4 sm:px-8 py-8 sm:py-12 flex flex-col items-center text-center">
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight">Pindai QR code pemilih</h1>
            <p class="mt-2 text-slate-500 max-w-md">Arahkan QR code pada kartu pemilih ke kamera, lalu tahan sebentar.</p>

            <div class="relative mt-8 w-full rounded-3xl overflow-hidden bg-ink border border-line shadow-[0_20px_40px_-24px_rgba(15,31,51,.45)]">
                <div id="reader"></div>
                <!-- Laser: ukuran kotak disesuaikan oleh JS dengan area baca QR -->
                <div id="laser-overlay" class="hidden absolute inset-0 pointer-events-none flex items-center justify-center" aria-hidden="true">
                    <div id="laser-box" class="relative">
                        <div class="laser-line"></div>
                    </div>
                </div>
            </div>

            <div id="scan-status" role="status" aria-live="polite"
                 class="mt-6 inline-flex items-center gap-2 rounded-full bg-white border border-line px-4 py-2 text-sm font-medium text-slate-600">
                <span id="scan-dot" class="w-2 h-2 rounded-full bg-slate-300"></span>
                <span id="scan-status-text">Menyalakan kamera…</span>
            </div>

            <p id="scan-help" class="hidden mt-3 max-w-md text-sm text-slate-500"></p>
            <button id="btn-retry-camera" type="button"
                    class="hidden mt-4 rounded-xl bg-brand hover:bg-brand-dark text-white font-semibold px-6 py-3 transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">
                Nyalakan kamera lagi
            </button>
        </div>
    </main>

    <!-- ================= HALAMAN 2: SURAT SUARA ================= -->
    <main id="page-voting" class="hidden flex-1">
        <div class="max-w-6xl mx-auto px-4 sm:px-8 pt-8 pb-40">
            <div class="text-center">
                <img src="{{ asset('img/assets/logo_kabupaten_karawang.png') }}" alt="Logo Panitia Kabupaten Karawang"
                     class="mx-auto mb-4 h-20 sm:h-24 w-auto" draggable="false" onerror="this.style.display='none'">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight">SURAT SUARA</h1>
                <p class="mt-1 text-lg font-semibold text-brand">PEMILIHAN KEPALA DESA CURUG</p>
                <p class="mt-1 text-lg font-semibold text-brand">TAHUN 2026</p>
                <p class="mt-3 text-slate-500">Sentuh foto calon pilihan Anda, lalu tekan tombol di bawah.</p>
            </div>

            <div id="candidate-grid" class="mt-8 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 sm:gap-5"></div>
        </div>

        <!-- Tombol pilih: selalu aktif, menempel di bawah layar -->
        <div class="fixed bottom-0 inset-x-0 z-30 bg-white/95 backdrop-blur border-t border-line px-4 sm:px-8 pt-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
            <div class="max-w-6xl mx-auto">
                <button id="btn-pilih-utama" type="button"
                        class="w-full h-14 rounded-xl bg-brand hover:bg-brand-dark active:bg-brand-dark text-white font-bold text-lg tracking-wide transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">
                    PILIH KANDIDAT INI
                </button>
            </div>
        </div>
    </main>

    <!-- ================= MODAL: SUDAH MEMILIH ================= -->
    <div id="warning-modal" role="dialog" aria-modal="true" aria-labelledby="warning-title"
         class="fixed inset-0 z-50 bg-ink/60 backdrop-blur-sm flex items-center justify-center p-4 opacity-0 pointer-events-none transition-opacity duration-200">
        <div data-panel class="bg-white rounded-3xl w-full max-w-md p-8 text-center shadow-2xl scale-95 transition-transform duration-200">
            <div class="mx-auto w-16 h-16 rounded-full bg-red-50 text-red-600 flex items-center justify-center">
                <svg class="w-9 h-9" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01"></path>
                </svg>
            </div>
            <h2 id="warning-title" class="mt-5 text-xl font-bold">Maaf, Anda sudah memberikan suara!</h2>
            <p class="mt-2 text-slate-500">QR code ini sudah dipakai dan tidak bisa digunakan lagi.</p>
            <button id="btn-kembali-kamera" type="button"
                    class="mt-7 w-full h-12 rounded-xl bg-brand hover:bg-brand-dark text-white font-semibold transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">
                Kembali ke kamera
            </button>
        </div>
    </div>

    <!-- ================= MODAL: KONFIRMASI ================= -->
    <div id="modal-konfirmasi" role="dialog" aria-modal="true" aria-labelledby="konfirmasi-title"
         class="fixed inset-0 z-50 bg-ink/60 backdrop-blur-sm flex items-center justify-center p-4 opacity-0 pointer-events-none transition-opacity duration-200">
        <div data-panel class="bg-white rounded-3xl w-full max-w-md p-6 sm:p-8 text-center shadow-2xl scale-95 transition-transform duration-200 max-h-[92dvh] overflow-y-auto">
            <h2 id="konfirmasi-title" class="text-2xl font-bold">Apakah Anda yakin?</h2>
            <div id="dynamic-modal-body" class="mt-6 flex justify-center"></div>
            <div class="mt-7 grid grid-cols-2 gap-3">
                <button id="btn-ulangi" type="button"
                        class="h-14 rounded-xl border-2 border-red-200 bg-white text-red-700 hover:bg-red-50 font-bold text-lg transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600">
                    ULANGI
                </button>
                <button id="btn-yakin" type="button"
                        class="h-14 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-lg transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600">
                    YAKIN
                </button>
            </div>
        </div>
    </div>

    <!-- ================= MODAL: SUKSES + SIMULASI CETAK STRUK ================= -->
    <div id="modal-sukses" role="dialog" aria-modal="true" aria-labelledby="sukses-title"
         class="fixed inset-0 z-[60] bg-ink/60 backdrop-blur-sm flex items-center justify-center p-4 opacity-0 pointer-events-none transition-opacity duration-200">
        <div data-panel class="bg-white rounded-3xl w-full max-w-md p-8 text-center shadow-2xl scale-95 transition-transform duration-200 overflow-hidden relative">
            <div class="mx-auto w-20 h-20 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center">
                <svg class="w-11 h-11" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <h2 id="sukses-title" class="mt-5 text-2xl font-bold">Terima kasih</h2>
            <p class="mt-1 text-slate-600 font-medium">Suara Anda berhasil disimpan.</p>
            <p id="print-status" class="mt-5 rounded-lg bg-brand-tint text-brand text-xs font-medium py-2 px-3 break-words whitespace-pre-line">Menyiapkan struk…</p>
            <p class="mt-5 text-sm text-slate-400">Kembali ke layar scan otomatis…</p>
            <div class="absolute bottom-0 left-0 h-1.5 w-full bg-slate-100">
                <div id="reset-progress" class="h-full bg-brand" style="width:100%"></div>
            </div>
        </div>
    </div>

    <!-- Area struk: kosong di layar, baru terlihat saat window.print() -->
    <div id="struk-print" aria-hidden="true"></div>

    <script>
        // ====================== PENGATURAN ======================
        // Lama pop-up sukses tampil sebelum aplikasi kembali ke layar scan (docx: 3-5 detik)
        const AUTO_RESET_MS = 3000;

        // Simulasi: ID pemilih yang dianggap sudah memakai hak suara
        // const USED_IDS = new Set(["12345"]);

        // Data kandidat dari database (tabel kandidat), dikirim oleh VotingController@index.
        // Bentuk: [{ id, no, nama, foto }]  ->  id = kunci database, no = nomor urut (tampilan/struk), foto = URL atau null
        const CANDIDATES = @json($kandidat);

        // ====================== STATE ======================
        let currentVoterId = null;
        let selectedCandidate = null;
        let scanLocked = false;
        let scannerStarting = false;
        let resetTimer = null;
        let isSubmitting = false;   // cegah klik ganda tombol YAKIN

        // ====================== ELEMEN ======================
        const $ = (id) => document.getElementById(id);
        const pageScan = $('page-scan');
        const pageVoting = $('page-voting');
        const candidateGrid = $('candidate-grid');

        const warningModal = $('warning-modal');
        const modalKonfirmasi = $('modal-konfirmasi');
        const modalSukses = $('modal-sukses');

        const scanDot = $('scan-dot');
        const scanStatusText = $('scan-status-text');
        const scanHelp = $('scan-help');
        const btnRetryCamera = $('btn-retry-camera');

        // ====================== UTILITAS ======================
        function esc(s) {
            return String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        }

        function openModal(modal) {
            modal.classList.remove('opacity-0', 'pointer-events-none');
            modal.querySelector('[data-panel]').classList.remove('scale-95');
        }

        function closeModal(modal) {
            modal.classList.add('opacity-0', 'pointer-events-none');
            modal.querySelector('[data-panel]').classList.add('scale-95');
        }

        // Kotak foto 3:4. Ikon placeholder ada di bawah foto; kalau file foto tidak ketemu, foto dihapus dan ikon tetap terlihat.
        function photoBox(c) {
            return `
                <div class="relative aspect-[3/4] bg-slate-100 overflow-hidden">
                    <svg class="absolute inset-0 m-auto w-1/2 h-1/2 text-slate-300" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z" />
                    </svg>
                    ${c.foto ? `<img src="${esc(c.foto)}" alt="Foto ${esc(c.nama)}" draggable="false"
                         class="absolute inset-0 w-full h-full object-cover" onerror="this.remove()">` : ''}
                </div>`;
        }

        // ====================== 1. SCANNER ======================
        const html5QrCode = new Html5Qrcode("reader", {
            formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE],
            useBarCodeDetectorIfSupported: true,   // pakai BarcodeDetector bawaan browser jika ada
            verbose: false
        });

        // Status library: 1 = belum mulai, 2 = memindai, 3 = jeda
        const SCANNER_NOT_STARTED = 1;
        const SCANNER_PAUSED = 3;

        // Area baca: persegi 80% dari sisi terpendek video (laser memakai rasio yang sama)
        const QR_BOX_RATIO = 0.8;
        const qrboxSize = (w, h) => {
            const side = Math.floor(Math.min(w, h) * QR_BOX_RATIO);
            return { width: side, height: side };
        };

        // ---- Garis laser: dipasang pas di dalam area baca ----
        const laserOverlay = $('laser-overlay');
        const laserBox = $('laser-box');

        function updateLaser() {
            const video = document.querySelector('#reader video');
            if (!video) return;
            const side = Math.floor(Math.min(video.clientWidth, video.clientHeight) * QR_BOX_RATIO);
            if (side > 0) {
                laserBox.style.width = side + 'px';
                laserBox.style.height = side + 'px';
                laserBox.style.setProperty('--box', side + 'px');
            }
        }

        function setLaser(on) {
            if (on) {
                updateLaser();
                laserOverlay.classList.remove('hidden');
            } else {
                laserOverlay.classList.add('hidden');
            }
        }

        // Ukuran video baru diketahui setelah kamera menyala atau layar diubah ukurannya
        new ResizeObserver(updateLaser).observe($('reader'));

        const BASE_CONFIG = { fps: 30, qrbox: qrboxSize, disableFlip: true };

        // Coba resolusi tinggi dulu; kalau kamera menolak, turun ke pengaturan paling sederhana
        const SCAN_ATTEMPTS = [
            {
                camera: { facingMode: "environment" },
                cfg: {
                    videoConstraints: {
                        facingMode: { ideal: "environment" },
                        width: { ideal: 1280, min: 640},
                        height: { ideal: 720, min: 480 },
                        frameRate: { ideal: 30 }
                    }
                }
            },
            { camera: { facingMode: "user" }, cfg: {} }
        ];

        function setStatus(text, state) {
            scanStatusText.textContent = text;
            scanDot.className = 'w-2 h-2 rounded-full ' + (
                state === 'ready' ? 'bg-emerald-500 animate-pulse' :
                state === 'error' ? 'bg-red-500' : 'bg-slate-300'
            );
        }

        function showCameraError(message) {
            setLaser(false);
            setStatus('Kamera tidak aktif', 'error');
            scanHelp.textContent = message;
            scanHelp.classList.remove('hidden');
            btnRetryCamera.classList.remove('hidden');
        }

        function describeCameraError(err) {
            const text = String(err || '');
            if (/NotAllowed|Permission/i.test(text)) {
                return 'Akses kamera ditolak. Izinkan kamera lewat ikon gembok di address bar, lalu tekan tombol di bawah.';
            }
            if (/NotFound|Requested device not found/i.test(text)) {
                return 'Kamera tidak ditemukan. Sambungkan webcam, lalu tekan tombol di bawah.';
            }
            if (/NotReadable|TrackStart|in use/i.test(text)) {
                return 'Kamera sedang dipakai aplikasi atau tab lain. Tutup yang lain, lalu tekan tombol di bawah.';
            }
            return 'Kamera tidak bisa dibuka. Pastikan kamera terpasang dan tidak dipakai aplikasi lain, lalu coba lagi.';
        }

        // Fokus otomatis kontinu, hanya jika didukung kamera
        function tuneCamera() {
            try {
                const video = document.querySelector('#reader video');
                const track = video && video.srcObject && video.srcObject.getVideoTracks()[0];
                if (!track || !track.getCapabilities) return;
                const caps = track.getCapabilities();
                if (caps.focusMode && caps.focusMode.includes('continuous')) {
                    track.applyConstraints({ advanced: [{ focusMode: 'continuous' }] }).catch(() => {});
                }
            } catch (e) { /* abaikan: tidak semua kamera mendukung */ }
        }

        async function startScanner() {
            if (scannerStarting) return;
            scannerStarting = true;
            scanHelp.classList.add('hidden');
            btnRetryCamera.classList.add('hidden');
            setLaser(false);
            setStatus('Menyalakan kamera…', 'loading');

            if (!window.isSecureContext) {
                scannerStarting = false;
                showCameraError('Kamera hanya bisa dipakai lewat alamat HTTPS atau localhost. Buka aplikasi lewat alamat yang diawali https://.');
                return;
            }

            let lastErr = null;
            for (const attempt of SCAN_ATTEMPTS) {
                try {
                    await html5QrCode.start(attempt.camera, { ...BASE_CONFIG, ...attempt.cfg }, onScanSuccess, () => {});
                    scannerStarting = false;
                    scanLocked = false;
                    setStatus('Menunggu QR code…', 'ready');
                    setLaser(true);
                    tuneCamera();
                    return;
                } catch (err) {
                    lastErr = err;
                    if (/NotAllowed|Permission/i.test(String(err))) break; // percuma dicoba ulang
                }
            }
            scannerStarting = false;
            showCameraError(describeCameraError(lastErr));
        }

        function resumeScanner() {
            scanLocked = false;
            setStatus('Menunggu QR code…', 'ready');
            try {
                const state = html5QrCode.getState();
                if (state === SCANNER_PAUSED) { html5QrCode.resume(); setLaser(true); }
                else if (state === SCANNER_NOT_STARTED) startScanner();
            } catch (e) {
                startScanner();
            }
        }

async function onScanSuccess(decodedText) {
    if (scanLocked) return;       
    scanLocked = true;
    try { html5QrCode.pause(true); } catch (e) {}
    setLaser(false);

    currentVoterId = String(decodedText).trim();
    setStatus('Memeriksa data ke server...', 'loading');

    try {
        // Nembak rute API yang udah kita bikin di backend
        let response = await fetch('/api/cek-pemilih', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ qr_id: currentVoterId })
        });

        let result = await response.json();

        if (response.ok && result.status === 'ok') {
            // Skenario: Data ada dan belum milih
            setStatus('QR code valid', 'ready');
            showVoting();
        } else if (response.status === 403 && result.status === 'sudah_memilih') {
            // Skenario: Data ada tapi status_memilih = 1
            openModal(warningModal);
        } else {
            // Skenario: QR Code tidak ada di database (bodong)
            scanLocked = false;
            setStatus('QR tidak terdaftar!', 'error');
            alert(result.pesan || (response.status === 429
                ? 'Terlalu banyak percobaan. Tunggu sebentar lalu coba lagi.'
                : 'QR Code tidak dapat diproses. Silakan coba lagi.')); // Munculin peringatan
            setTimeout(resumeScanner, 2500); // Nyalain kamera lagi setelah 2.5 detik
        }
    } catch (error) {
        scanLocked = false;
        setStatus('Koneksi terputus', 'error');
        alert("Gagal menghubungi server. Pastikan server nyala.");
        setTimeout(resumeScanner, 2000);
    }
}

        btnRetryCamera.addEventListener('click', startScanner);

        $('btn-kembali-kamera').addEventListener('click', () => {
            closeModal(warningModal);
            setTimeout(resumeScanner, 300);
        });

        // ====================== 2. SURAT SUARA ======================
        function renderCandidates() {
            if (!CANDIDATES.length) {
                candidateGrid.innerHTML = '<p class="col-span-full text-center text-slate-500 py-10">Belum ada kandidat terdaftar. Silakan hubungi panitia.</p>';
                return;
            }
            candidateGrid.innerHTML = CANDIDATES.map((c) => `
                <button type="button" class="candidate-card relative text-left rounded-2xl bg-white border border-line overflow-hidden flex flex-col"
                        data-no="${c.no}" aria-pressed="false" aria-label="Calon nomor ${c.no}, ${esc(c.nama)}">
                    <div class="relative">
                        ${photoBox(c)}
                        <span class="absolute top-3 left-3 w-10 h-10 rounded-full bg-white text-brand font-extrabold text-lg flex items-center justify-center shadow">${c.no}</span>
                        <span class="check absolute top-3 right-3 w-9 h-9 rounded-full bg-brand text-white flex items-center justify-center shadow">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        </span>
                    </div>
                    <div class="px-3 py-3 text-center font-semibold text-sm sm:text-base leading-snug">${esc(c.nama)}</div>
                </button>
            `).join('');

            candidateGrid.querySelectorAll('.candidate-card').forEach((card) => {
                card.addEventListener('click', () => {
                    const no = Number(card.dataset.no);
                    selectedCandidate = CANDIDATES.find((c) => c.no === no);
                    candidateGrid.querySelectorAll('.candidate-card').forEach((c) => c.setAttribute('aria-pressed', String(c === card)));
                });
            });
        }

        function clearSelection() {
            selectedCandidate = null;
            candidateGrid.querySelectorAll('.candidate-card').forEach((c) => c.setAttribute('aria-pressed', 'false'));
        }

        function showVoting() {
            clearSelection();
            pageScan.classList.add('hidden');
            pageVoting.classList.remove('hidden');
            window.scrollTo(0, 0);
        }

        // "PILIH KANDIDAT INI" -> pop-up konfirmasi (selalu aktif, boleh tanpa memilih calon)
        $('btn-pilih-utama').addEventListener('click', () => {
            const body = $('dynamic-modal-body');
            if (selectedCandidate) {
                body.innerHTML = `
                    <div class="w-44 rounded-2xl bg-white overflow-hidden border border-line" style="box-shadow:0 0 0 3px #1b5b9c">
                        <div class="relative">
                            ${photoBox(selectedCandidate)}
                            <span class="absolute top-2 left-2 w-9 h-9 rounded-full bg-white text-brand font-extrabold flex items-center justify-center shadow">${selectedCandidate.no}</span>
                        </div>
                        <div class="px-2 py-3 font-semibold text-sm bg-brand-tint">${esc(selectedCandidate.nama)}</div>
                    </div>`;
            } else {
                body.innerHTML = `
                    <div class="rounded-2xl bg-amber-50 border border-amber-200 px-5 py-6 text-amber-900">
                        <p class="font-semibold">Anda belum memilih calon.</p>
                        <p class="mt-1 text-sm">Suara Anda akan dianggap <span class="font-bold text-red-600">TIDAK SAH</span>.</p>
                    </div>`;
            }
            openModal(modalKonfirmasi);
        });

        // "ULANGI" -> kembali ke daftar calon dalam kondisi ter-reset
        $('btn-ulangi').addEventListener('click', () => {
            closeModal(modalKonfirmasi);
            clearSelection();
        });

        // "YAKIN" -> pop-up sukses, simulasi cetak struk, lalu auto-reset ke layar scan
$('btn-yakin').addEventListener('click', async () => {
    if (isSubmitting) return;     // klik ganda: abaikan, permintaan pertama sedang diproses
    isSubmitting = true;
    closeModal(modalKonfirmasi);

    // Data struk diambil sekarang. Tab PDF dibuka di dalam klik ini agar tidak diblokir pop-up blocker.
    const candidate = selectedCandidate;
    const mode = getStrukMode();
    const pdfWin = mode === 'pdf' ? window.open('', '_blank') : null;
    if (pdfWin) pdfWin.document.write('<title>Struk</title><p style="font:16px sans-serif">Menyiapkan struk…</p>');
    const tutupTabPdf = () => { if (pdfWin && !pdfWin.closed) pdfWin.close(); };

    try {
        // Nembak rute simpan suara di backend
        let response = await fetch('/api/simpan-suara', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                qr_id: currentVoterId,
                // Kirim ID kandidat dari database (bukan nomor urut). Kalau tidak ada yang dipilih (suara tidak sah), kirim null
                kandidat_id: candidate ? candidate.id : null
            })
        });

        let result = await response.json();

        if (response.ok && result.status === 'sukses') {
            // Struk dibuat di browser, hanya setelah suara benar-benar tersimpan. Tidak ada request ke server.
            const strukInfo = issueStruk(candidate, mode, pdfWin);

            // Identitas pemilih sengaja tidak ditampilkan/dicetak agar pilihan tidak bisa dikaitkan ke orangnya
            $('print-status').textContent =
                (candidate ? `Struk: SUARA SAH | Pilih: No.${candidate.no}` : 'Struk: SUARA TIDAK SAH') + '\n' + strukInfo;

            const bar = $('reset-progress');
            bar.style.animation = 'none';
            void bar.offsetWidth;
            bar.style.animation = `drain ${AUTO_RESET_MS}ms linear forwards`;

            openModal(modalSukses);
            clearTimeout(resetTimer);
            resetTimer = setTimeout(resetToScan, AUTO_RESET_MS);
        } else {
            tutupTabPdf();
            isSubmitting = false;
            alert("Gagal menyimpan suara: " + (result.pesan || "Silakan coba lagi."));
        }
    } catch (error) {
        tutupTabPdf();
        isSubmitting = false;
        alert("Terjadi kesalahan koneksi saat menyimpan suara.");
    }
});

        // ====================== STRUK ======================
        // Teks header struk (sesuai contoh PDF panitia)
        const STRUK_HEADER = ['PANITIA PILKADES', 'DESA CURUG KECAMATAN KLARI', 'TAHUN 2026'];

        // Isi QR = persis teks yang tercetak di bawah QR. Ubah di sini kalau formatnya berubah.
        function buildStrukPayload(candidate) {
            return candidate
                ? `[SUARA_SAH:${candidate.no}]`
                : '[SUARA_TIDAK_SAH:DATA_TIDAK_VALID]';
        }

        // Browser tidak bisa mendeteksi apakah printer terhubung, jadi mode dipilih operator:
        //   buka halaman dengan ?struk=printer atau ?struk=pdf (pilihan diingat di browser). Bawaan: pdf.
        function getStrukMode() {
            const q = new URLSearchParams(location.search).get('struk');
            if (q === 'printer' || q === 'pdf') {
                try { localStorage.setItem('struk_mode', q); } catch (e) {}
                return q;
            }
            try { return localStorage.getItem('struk_mode') === 'printer' ? 'printer' : 'pdf'; }
            catch (e) { return 'pdf'; }
        }

        function makeQr(text) {
            const qr = qrcode(0, 'M');      // ukuran otomatis, koreksi error level M
            qr.addData(text);
            qr.make();
            return qr;
        }

        // Mode printer: render struk ke area cetak lalu panggil dialog/perintah cetak
        function printStruk(payload) {
            const qr = makeQr(payload);
            const n = qr.getModuleCount();
            let d = '';
            for (let r = 0; r < n; r++) {
                for (let c = 0; c < n; c++) {
                    if (qr.isDark(r, c)) d += `M${c} ${r}h1v1h-1z`;
                }
            }
            $('struk-print').innerHTML = `
                <div class="sp-t1">${esc(STRUK_HEADER[0])}</div>
                <div class="sp-t2">${esc(STRUK_HEADER[1])}</div>
                <div class="sp-t2">${esc(STRUK_HEADER[2])}</div>
                <svg class="sp-qr" viewBox="-4 -4 ${n + 8} ${n + 8}" shape-rendering="crispEdges" xmlns="http://www.w3.org/2000/svg">
                    <rect x="-4" y="-4" width="${n + 8}" height="${n + 8}" fill="#fff"/>
                    <path d="${d}" fill="#000"/>
                </svg>
                <div class="sp-code">${esc(payload)}</div>`;
            requestAnimationFrame(() => requestAnimationFrame(() => window.print()));
        }

        // Mode PDF: bikin file PDF A4 (QR digambar vektor) dan kembalikan URL-nya
        function createStrukPdfUrl(payload) {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF({ unit: 'mm', format: 'a4', orientation: 'portrait' });
            doc.setProperties({ title: 'Struk Suara Pilkades Curug 2026' });
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(26); doc.text(STRUK_HEADER[0], 105, 32, { align: 'center' });
            doc.setFontSize(18); doc.text(STRUK_HEADER[1], 105, 42, { align: 'center' });
            doc.setFontSize(18); doc.text(STRUK_HEADER[2], 105, 50, { align: 'center' });

            const qr = makeQr(payload);
            const n = qr.getModuleCount();
            const quiet = 4, size = 170, y0 = 56;
            const x0 = (210 - size) / 2;
            const m = size / (n + quiet * 2);
            doc.setFillColor(0, 0, 0);
            for (let r = 0; r < n; r++) {
                for (let c = 0; c < n; c++) {
                    if (qr.isDark(r, c)) doc.rect(x0 + (c + quiet) * m, y0 + (r + quiet) * m, m + 0.02, m + 0.02, 'F');
                }
            }
            doc.setFontSize(16);
            doc.text(payload, 105, y0 + (quiet + n) * m + 12, { align: 'center' });
            return URL.createObjectURL(doc.output('blob'));
        }

        // Dijalankan sekali per pemilih. Mengembalikan teks status untuk pop-up sukses.
        function issueStruk(candidate, mode, pdfWin) {
            const payload = buildStrukPayload(candidate);
            try {
                if (mode === 'printer') {
                    printStruk(payload);
                    return 'Perintah cetak dikirim ke printer.';
                }
                const url = createStrukPdfUrl(payload);
                if (pdfWin && !pdfWin.closed) {
                    pdfWin.location.href = url;
                    setTimeout(() => URL.revokeObjectURL(url), 300000);
                    return 'Struk dibuka sebagai PDF di tab baru.';
                }
                URL.revokeObjectURL(url);
                return 'Tab PDF diblokir browser. Izinkan pop-up untuk situs ini.';
            } catch (e) {
                console.error('Struk gagal dibuat:', e);
                if (pdfWin && !pdfWin.closed) pdfWin.close();
                return 'Struk gagal dibuat. Cek koneksi internet (library QR/PDF dimuat dari CDN).';
            }
        }

        // ====================== 3. RESET KE LAYAR SCAN ======================
        function resetToScan() {
            clearTimeout(resetTimer);
            closeModal(modalSukses);
            clearSelection();
            currentVoterId = null;
            isSubmitting = false;

            pageVoting.classList.add('hidden');
            pageScan.classList.remove('hidden');
            window.scrollTo(0, 0);

            setTimeout(resumeScanner, 300);
        }

        // ====================== START ======================
        renderCandidates();
        window.addEventListener('load', startScanner);
    </script>
</body>
</html>
