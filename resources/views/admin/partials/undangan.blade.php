{{-- Satu lembar surat panggilan. Butuh variabel $pemilih (baris tabel pemilih, termasuk kode_qr). --}}
    <!-- Kertas Undangan -->
    <div class="max-w-2xl mx-auto bg-white p-10 border border-slate-300 shadow-xl print:shadow-none print:border-none print:max-w-full print:mx-0">
        
        <!-- Kop Surat -->
        <div class="text-center border-b-4 border-slate-800 pb-4 mb-6">
            <h1 class="text-xl font-bold uppercase tracking-wider">Panitia Pemilihan Kepala Desa Curug</h1>
            <h2 class="text-lg font-semibold uppercase">Kecamatan Klari - Kabupaten Karawang</h2>
            <p class="text-sm mt-1">Sekretariat: Kantor Kepala Desa Curug, Kec. Klari, Kab. Karawang</p>
        </div>

        <!-- Judul Surat -->
        <div class="text-center mb-8">
            <h3 class="text-lg font-bold underline">SURAT PANGGILAN PEMUNGUTAN SUARA</h3>
            <p class="text-sm font-semibold mt-1">Model C-6 / Pemberitahuan</p>
        </div>

        <!-- Isi Surat -->
        <div class="mb-6 leading-relaxed">
            <p>Bersama ini mengundang Bapak/Ibu/Saudara/i untuk hadir memberikan hak suara pada Pemilihan Kepala Desa Curug Tahun 2026, yang data dirinya tercantum di bawah ini:</p>
        </div>

        <!-- Data Pemilih & QR Code -->
        <div class="flex justify-between items-start mb-8 p-4 border-2 border-slate-200 rounded-xl">
            <div class="flex-1">
                <table class="w-full text-sm">
                    <tr>
                        <td class="py-2 font-semibold w-32">Nomor Urut DPT</td>
                        <td class="py-2 px-2">:</td>
                        <td class="py-2 font-bold text-lg text-[#1b5b9c]">{{ $pemilih->no_urut_dpt }}</td>
                    </tr>
                    <tr>
                        <td class="py-2 font-semibold">Nama Lengkap</td>
                        <td class="py-2 px-2">:</td>
                        <td class="py-2 font-bold text-lg uppercase">{{ $pemilih->nama }}</td>
                    </tr>
                    <tr>
                        <td class="py-2 font-semibold">Jenis Kelamin</td>
                        <td class="py-2 px-2">:</td>
                        <td class="py-2">{{ $pemilih->jenis_kelamin == 'L' ? 'Laki-Laki' : 'Perempuan' }}</td>
                    </tr>
                    <tr>
                        <td class="py-2 font-semibold">Usia</td>
                        <td class="py-2 px-2">:</td>
                        <td class="py-2">{{ $pemilih->umur }} Tahun</td>
                    </tr>
                    <tr>
                        <td class="py-2 font-semibold">Lokasi TPS</td>
                        <td class="py-2 px-2">:</td>
                        <td class="py-2 font-bold">TPS {{ str_pad($pemilih->no_tps, 2, '0', STR_PAD_LEFT) }}</td>
                    </tr>
                </table>
            </div>

            <!-- Posisi QR Code -->
            <div class="flex flex-col items-center justify-center bg-slate-50 p-3 rounded-lg border border-slate-200 ml-4">
                <div class="bg-white p-2 mb-2">
                    {{-- Isi QR = kode acak pribadi (kode_qr), BUKAN NIK --}}
                    {!! QrCode::size(120)->generate($pemilih->kode_qr) !!}
                </div>
                <p class="text-[10px] text-slate-500 font-semibold">Pindai saat di TPS</p>
                <p class="text-[10px] text-red-600 font-semibold mt-1">Jangan difoto atau dibagikan</p>
            </div>
        </div>

        <!-- Penutup & Waktu Pelaksanaan -->
        <div class="mb-8">
            <p class="mb-2">Pemungutan suara akan dilaksanakan pada:</p>
            <table class="w-full text-sm ml-4 mb-4 font-semibold">
                <tr>
                    <td class="py-1 w-24">Hari/Tanggal</td>
                    <td class="py-1 px-2">:</td>
                    <td class="py-1">Minggu, 22 Nopember 2026</td>
                </tr>
                <tr>
                    <td class="py-1">Waktu</td>
                    <td class="py-1 px-2">:</td>
                    <td class="py-1">07.00 WIB s/d Selesai</td>
                </tr>
            </table>
            <p class="text-sm italic text-red-600 font-semibold mb-2">Catatan Penting:</p>
            <ul class="list-disc list-inside text-sm text-slate-700 space-y-1 ml-2">
                <li>Harap membawa surat undangan ini beserta <b>KTP Asli / KK Asli</b>.</li>
                <li>QR Code di atas bersifat rahasia dan hanya dapat digunakan 1 (satu) kali.</li>
                <li>Gunakan hak pilih Anda dengan bijak, rahasia, dan tanpa paksaan.</li>
            </ul>
        </div>

        <!-- TTD -->
        <div class="flex justify-end text-center mt-12">
            <div>
                <p class="text-sm mb-16">Curug, Agustus 2026<br>Ketua Panitia Pilkades</p>
                <p class="font-bold underline">HADIAN JATNIKA, ST</p>
            </div>
        </div>

    </div>
