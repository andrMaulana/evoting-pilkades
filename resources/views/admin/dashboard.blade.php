@extends('layouts.admin')

@section('content')
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Dashboard Real-Time</h1>
            <p class="text-slate-500 text-sm mt-1">Pantau perolehan suara dan partisipasi warga secara langsung.</p>
        </div>
        <!-- Tombol Refresh Manual (Bisa juga dibikin auto-refresh nanti) -->
        <button onclick="window.location.reload()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-lg transition-colors flex items-center gap-2 text-sm">
            🔄 Segarkan Data
        </button>
    </div>
    
    <!-- Baris 1: Kartu Metrik Utama -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Card DPT -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm border-l-4 border-l-blue-500">
            <p class="text-slate-500 text-sm font-medium">Total DPT</p>
            <h3 class="text-3xl font-bold text-slate-800 mt-2">{{ $totalDpt }} <span class="text-base font-normal text-slate-500">Jiwa</span></h3>
        </div>
        <!-- Card Sudah Memilih -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm border-l-4 border-l-emerald-500">
            <p class="text-slate-500 text-sm font-medium">Sudah Memilih</p>
            <h3 class="text-3xl font-bold text-slate-800 mt-2">{{ $sudahMilih }} <span class="text-base font-normal text-slate-500">Suara</span></h3>
        </div>
        <!-- Card Belum Memilih -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm border-l-4 border-l-amber-500">
            <p class="text-slate-500 text-sm font-medium">Belum Memilih</p>
            <h3 class="text-3xl font-bold text-slate-800 mt-2">{{ $belumMilih }} <span class="text-base font-normal text-slate-500">Pemilih</span></h3>
        </div>
        <!-- Card Status Suara -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm border-l-4 border-l-purple-500">
            <p class="text-slate-500 text-sm font-medium">Suara Sah / Tidak Sah</p>
            <h3 class="text-xl font-bold text-slate-800 mt-2">
                <span class="text-emerald-600">{{ $suaraSah }} Sah</span> <span class="text-slate-300 mx-2">|</span> <span class="text-red-500">{{ $suaraTidakSah }} Batal</span>
            </h3>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Kolom Kiri: Quick Count Kandidat (Porsi 2/3) -->
        <div class="lg:col-span-2 bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
            <h2 class="text-lg font-bold text-slate-800 mb-6">Perolehan Suara Kandidat</h2>
            
            <div class="space-y-6">
                @foreach($kandidat as $k)
                    @php
                        // Hitung persentase (cegah error dibagi nol)
                        $persen = $suaraSah > 0 ? round(($k->total_suara / $suaraSah) * 100, 1) : 0;
                    @endphp
                    <div>
                        <div class="flex justify-between items-end mb-2">
                            <span class="font-semibold text-slate-700">No.{{ $k->no_urut }} - {{ $k->nama }}</span>
                            <div class="text-right">
                                <span class="font-bold text-lg text-[#1b5b9c]">{{ $k->total_suara }}</span>
                                <span class="text-slate-500 text-sm ml-1">Suara ({{ $persen }}%)</span>
                            </div>
                        </div>
                        <!-- Progress Bar -->
                        <div class="w-full bg-slate-100 rounded-full h-4 overflow-hidden">
                            <div class="bg-[#1b5b9c] h-4 rounded-full transition-all duration-1000" style="width: {{ $persen }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
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
                        <h3 class="text-2xl font-bold">{{ $pemilihLaki }} <span class="text-sm font-normal text-slate-500">Jiwa</span></h3>
                    </div>
                </div>
                <!-- Bar Perempuan -->
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-pink-50 flex items-center justify-center text-pink-600 font-bold text-xl">P</div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-slate-600 mb-1">Perempuan</p>
                        <h3 class="text-2xl font-bold">{{ $pemilihPerempuan }} <span class="text-sm font-normal text-slate-500">Jiwa</span></h3>
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
@endsection