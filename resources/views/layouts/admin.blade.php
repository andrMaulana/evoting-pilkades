<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ruang Kendali - Pilkades Curug</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Plus Jakarta Sans', sans-serif; } </style>
</head>
<body class="bg-slate-50 min-h-screen flex text-slate-800">

    <!-- Sidebar Menu -->
    <aside class="w-64 bg-white border-r border-slate-200 flex flex-col fixed h-full z-10">
        <div class="p-6 border-b border-slate-100 text-center">
            <h2 class="text-xl font-bold text-[#1b5b9c]">Admin Pilkades</h2>
            <p class="text-xs text-slate-500 mt-1">Desa Curug 2026</p>
        </div>

        <nav class="flex-1 p-4 space-y-2">
            <a href="/admin/dashboard" class="block px-4 py-3 rounded-xl bg-slate-50 text-[#1b5b9c] font-semibold transition-colors border border-slate-100">
                📊 Dashboard
            </a>
            <!-- Menu Data Pemilih (Nanti di Tahap 4) -->
            <a href="/admin/pemilih" class="block px-4 py-3 rounded-xl hover:bg-slate-50 text-slate-600 font-medium transition-colors">
                👥 Data Pemilih (DPT)
            </a>
            <a href="/admin/kandidat" class="block px-4 py-3 rounded-xl hover:bg-slate-50 text-slate-600 font-medium transition-colors">
                🗳️ Data Kandidat
            </a>
        </nav>

        <!-- Tombol Logout -->
        <div class="p-4 border-t border-slate-100">
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="w-full flex items-center justify-center gap-2 bg-red-50 hover:bg-red-100 text-red-600 font-bold py-3 rounded-xl transition-all">
                    LOGOUT
                </button>
            </form>
        </div>
    </aside>

    <!-- Area Konten Utama -->
    <main class="flex-1 ml-64 p-8">
        @yield('content')
    </main>

</body>
</html>