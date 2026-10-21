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
                    <td class="px-6 py-4 text-center">
                        @if($p->status_memilih == 1)
                            <span class="inline-block px-3 py-1 bg-emerald-100 text-emerald-700 rounded-full text-xs font-bold">Sudah</span>
                        @else
                            <span class="inline-block px-3 py-1 bg-amber-100 text-amber-700 rounded-full text-xs font-bold">Belum</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-right">
<!-- Ganti tombol Edit jadi ini -->
                        <button onclick="bukaModalEdit('{{ $p->nik }}', '{{ $p->no_urut_dpt }}', '{{ $p->nama }}', '{{ $p->jenis_kelamin }}', '{{ $p->umur }}')" class="text-[#1b5b9c] hover:underline text-xs font-bold mr-3">Edit</button>
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
                    <td colspan="7" class="px-6 py-8 text-center text-slate-500">
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
                <div class="pt-4 flex gap-3">
                    <button type="button" onclick="document.getElementById('modalEdit').classList.add('hidden')" class="flex-1 px-4 py-2 bg-slate-100 text-slate-600 font-semibold rounded-lg hover:bg-slate-200">Batal</button>
                    <button type="submit" class="flex-1 px-4 py-2 bg-[#1b5b9c] text-white font-semibold rounded-lg hover:bg-[#154a80]">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Script Pelempar Data ke Modal -->
    <script>
        function bukaModalEdit(nik, no_urut, nama, jk, umur) {
            // Isi form dengan data warga yang dipilih
            document.getElementById('edit_nik').value = nik;
            document.getElementById('edit_no_urut').value = no_urut;
            document.getElementById('edit_nama').value = nama;
            document.getElementById('edit_jk').value = jk;
            document.getElementById('edit_umur').value = umur;
            
            // Ubah tujuan URL action sesuai NIK warga
            document.getElementById('formEdit').action = '/admin/pemilih/update/' + nik;
            
            // Tampilkan modalnya
            document.getElementById('modalEdit').classList.remove('hidden');
        }
    </script>
@endsection