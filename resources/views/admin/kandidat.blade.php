@extends('layouts.admin')

@section('content')

    <!-- (Bagian Toast Alert tetap sama) -->
    @if(session('success'))
    <div id="toast-success" class="fixed top-5 right-5 z-50 flex items-center w-full max-w-xs p-4 text-slate-600 bg-white rounded-xl shadow-lg border border-emerald-100 transition-all duration-500">
        <div class="inline-flex items-center justify-center flex-shrink-0 w-10 h-10 text-emerald-500 bg-emerald-50 rounded-lg">✓</div>
        <div class="ml-3 text-sm font-semibold">{{ session('success') }}</div>
        <button onclick="this.parentElement.remove()" class="ml-auto text-slate-400 hover:text-red-500">✕</button>
    </div>
    <script>setTimeout(() => { document.getElementById('toast-success')?.remove(); }, 3000);</script>
    @endif

    @if($errors->any())
    <div id="toast-error" class="fixed top-5 right-5 z-50 flex items-center w-full max-w-xs p-4 text-slate-600 bg-white rounded-xl shadow-lg border border-red-100 transition-all duration-500">
        <div class="inline-flex items-center justify-center flex-shrink-0 w-10 h-10 text-red-500 bg-red-50 rounded-lg">✕</div>
        <div class="ml-3 text-sm font-semibold">{{ $errors->first() }}</div>
        <button onclick="this.parentElement.remove()" class="ml-auto text-slate-400 hover:text-red-500">✕</button>
    </div>
    <script>setTimeout(() => { document.getElementById('toast-error')?.remove(); }, 4000);</script>
    @endif

    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Manajemen Kandidat</h1>
            <p class="text-slate-500 text-sm mt-1">Kelola data dan foto calon Kepala Desa Curug.</p>
        </div>
        <button onclick="document.getElementById('modalTambah').classList.remove('hidden')" class="px-4 py-2 bg-[#1b5b9c] hover:bg-[#154a80] text-white font-semibold rounded-lg transition-colors flex items-center gap-2 text-sm">
            + Tambah Kandidat
        </button>
    </div>

    <!-- Grid Card Kandidat -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($kandidat as $k)
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
            <div class="bg-slate-50 p-6 flex flex-col items-center justify-center border-b border-slate-100">
                
                <!-- Tampilkan Foto Asli, Kalau Kosong Tampilkan Placeholder -->
                @if($k->foto_url)
                    <img src="{{ $k->foto_url }}" alt="{{ $k->nama }}" class="w-32 h-32 object-cover rounded-full border-4 border-white shadow-md mb-4">
                @else
                    <div class="w-32 h-32 bg-slate-200 rounded-full flex items-center justify-center border-4 border-white shadow-md mb-4">
                        <span class="text-4xl text-slate-400 font-bold">👤</span>
                    </div>
                @endif
                
                <span class="px-3 py-1 bg-amber-100 text-amber-700 rounded-full text-xs font-bold mb-2">Kandidat No. {{ $k->no_urut }}</span>
                <h3 class="text-lg font-bold text-slate-800 text-center uppercase">{{ $k->nama }}</h3>
            </div>
            <div class="p-4 bg-white flex justify-between items-center mt-auto">
                <button onclick="bukaModalEdit('{{ $k->id }}', '{{ $k->no_urut }}', '{{ $k->nama }}')" class="text-[#1b5b9c] hover:bg-blue-50 px-3 py-1.5 rounded-lg text-sm font-bold transition-colors">Edit Data</button>
                
                <form action="{{ route('admin.kandidat.destroy', $k->id) }}" method="POST" onsubmit="return confirm('Yakin ingin menghapus kandidat ini?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-red-500 hover:bg-red-50 px-3 py-1.5 rounded-lg text-sm font-bold transition-colors">Hapus</button>
                </form>
            </div>
        </div>
        @empty
        <div class="col-span-full bg-white p-10 rounded-2xl border border-slate-200 shadow-sm text-center">
            <p class="text-slate-500">Belum ada data kandidat. Silakan tambah kandidat baru.</p>
        </div>
        @endforelse
    </div>

    <!-- Modal Tambah (Perhatikan atribut enctype) -->
    <div id="modalTambah" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                <h3 class="font-bold text-slate-800">Tambah Kandidat</h3>
                <button onclick="document.getElementById('modalTambah').classList.add('hidden')" class="text-slate-400 hover:text-red-500 font-bold">✕</button>
            </div>
            <form action="{{ route('admin.kandidat.store') }}" method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Nomor Urut</label>
                    <input type="number" name="no_urut" required class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-[#1b5b9c] outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Nama Kandidat</label>
                    <input type="text" name="nama" required class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-[#1b5b9c] outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Foto (Opsional, Max 2MB)</label>
                    <input type="file" name="foto" accept="image/png, image/jpeg, image/jpg" class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-[#1b5b9c] outline-none text-sm file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-[#1b5b9c] hover:file:bg-blue-100">
                </div>
                <div class="pt-4 flex gap-3">
                    <button type="button" onclick="document.getElementById('modalTambah').classList.add('hidden')" class="flex-1 px-4 py-2 bg-slate-100 text-slate-600 font-semibold rounded-lg hover:bg-slate-200">Batal</button>
                    <button type="submit" class="flex-1 px-4 py-2 bg-[#1b5b9c] text-white font-semibold rounded-lg hover:bg-[#154a80]">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit (Perhatikan atribut enctype) -->
    <div id="modalEdit" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                <h3 class="font-bold text-slate-800">Edit Kandidat</h3>
                <button onclick="document.getElementById('modalEdit').classList.add('hidden')" class="text-slate-400 hover:text-red-500 font-bold">✕</button>
            </div>
            <form id="formEditKandidat" method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Nomor Urut</label>
                    <input type="number" name="no_urut" id="edit_kandidat_no" required class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-[#1b5b9c] outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Nama Kandidat</label>
                    <input type="text" name="nama" id="edit_kandidat_nama" required class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-[#1b5b9c] outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Ganti Foto Baru (Opsional)</label>
                    <input type="file" name="foto" accept="image/png, image/jpeg, image/jpg" class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-[#1b5b9c] outline-none text-sm file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-[#1b5b9c] hover:file:bg-blue-100">
                    <p class="text-xs text-slate-500 mt-1">*Biarkan kosong jika tidak ingin mengubah foto</p>
                </div>
                <div class="pt-4 flex gap-3">
                    <button type="button" onclick="document.getElementById('modalEdit').classList.add('hidden')" class="flex-1 px-4 py-2 bg-slate-100 text-slate-600 font-semibold rounded-lg hover:bg-slate-200">Batal</button>
                    <button type="submit" class="flex-1 px-4 py-2 bg-[#1b5b9c] text-white font-semibold rounded-lg hover:bg-[#154a80]">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function bukaModalEdit(id, no_urut, nama) {
            document.getElementById('edit_kandidat_no').value = no_urut;
            document.getElementById('edit_kandidat_nama').value = nama;
            document.getElementById('formEditKandidat').action = '/admin/kandidat/update/' + id;
            document.getElementById('modalEdit').classList.remove('hidden');
        }
    </script>
@endsection