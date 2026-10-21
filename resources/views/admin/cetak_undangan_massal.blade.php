<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Undangan Massal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .halaman { break-after: page; page-break-after: always; }
        .halaman:last-child { break-after: auto; page-break-after: auto; }
        @media print {
            .no-print { display: none !important; }
            @page { margin: 0; }
            body { margin: 1.5cm; }
        }
    </style>
</head>
<body class="bg-slate-200 text-slate-800 font-sans p-8 print:p-0 print:bg-white">

    <!-- Bilah atas (tidak ikut tercetak) -->
    <div class="max-w-2xl mx-auto mb-6 no-print">
        <div class="flex justify-between items-center">
            <a href="{{ route('admin.pemilih') }}" class="text-[#1b5b9c] font-semibold hover:underline">← Kembali ke DPT</a>
            @if($pemilih->count() > 0)
            <button onclick="window.print()" class="px-6 py-2 bg-slate-800 hover:bg-slate-900 text-white font-bold rounded-lg shadow-lg">
                🖨️ Cetak {{ $pemilih->count() }} Undangan (Ctrl+P)
            </button>
            @endif
        </div>

        <p class="mt-3 text-sm text-slate-600">
            Menampilkan <b>{{ $pemilih->count() }}</b> dari <b>{{ $total }}</b> pemilih yang cocok dengan filter.
        </p>
        @if($total > $pemilih->count())
        <p class="mt-1 text-sm text-amber-700 font-semibold">
            Dibatasi {{ $batas }} undangan sekali cetak. Pakai filter rentang No. Urut DPT untuk mencetak sisanya.
        </p>
        @endif
    </div>

    @forelse($pemilih as $p)
        <div class="halaman mb-8 print:mb-0">
            @include('admin.partials.undangan', ['pemilih' => $p])
        </div>
    @empty
        <div class="max-w-2xl mx-auto bg-white p-10 rounded-xl text-center text-slate-500 no-print">
            Tidak ada pemilih yang cocok dengan filter ini.
        </div>
    @endforelse

</body>
</html>
