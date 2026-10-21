<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Panitia - Pilkades Curug</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Plus Jakarta Sans', sans-serif; } </style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">

    <div class="bg-white p-8 sm:p-10 rounded-3xl shadow-[0_20px_40px_-24px_rgba(15,31,51,.15)] w-full max-w-md border border-slate-100">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Ruang Kendali Panitia</h1>
            <p class="text-slate-500 text-sm mt-2">Pilkades Desa Curug 2026</p>
        </div>

        <!-- Tempat munculin error kalau password salah -->
        @if($errors->any())
            <div class="bg-red-50 text-red-600 p-4 rounded-xl text-sm mb-6 text-center border border-red-100 font-medium">
                {{ $errors->first() }}
            </div>
        @endif

        <form action="/login" method="POST" class="space-y-5">
            @csrf
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Email Panitia</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus
                       class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#1b5b9c] focus:border-[#1b5b9c] focus:bg-white outline-none transition-all">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Password</label>
                <input type="password" name="password" required 
                       class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#1b5b9c] focus:border-[#1b5b9c] focus:bg-white outline-none transition-all">
            </div>
            <button type="submit" 
                    class="w-full bg-[#1b5b9c] hover:bg-[#154a80] active:scale-[0.98] text-white font-bold py-3.5 rounded-xl transition-all mt-4">
                MASUK KE DASHBOARD
            </button>
        </form>
    </div>

</body>
</html>