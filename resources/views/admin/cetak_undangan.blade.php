<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Undangan Pemilih - {{ $pemilih->nama }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            /* Sembunyikan tombol cetak pas kertasnya di-print */
            .no-print { display: none !important; }
            /* Hilangin margin bawaan browser */
            @page { margin: 0; }
            body { margin: 1.5cm; }
        }
    </style>
</head>
<body class="bg-slate-200 text-slate-800 font-sans p-8 print:p-0 print:bg-white">

    <!-- Tombol Print (Akan hilang saat dicetak) -->
    <div class="max-w-2xl mx-auto mb-6 no-print flex justify-between items-center">
        <a href="{{ route('admin.pemilih') }}" class="text-[#1b5b9c] font-semibold hover:underline">← Kembali ke DPT</a>
        <button onclick="window.print()" class="px-6 py-2 bg-slate-800 hover:bg-slate-900 text-white font-bold rounded-lg shadow-lg flex items-center gap-2">
            🖨️ Cetak Undangan (Ctrl+P)
        </button>
    </div>

    @include('admin.partials.undangan', ['pemilih' => $pemilih])

</body>
</html>