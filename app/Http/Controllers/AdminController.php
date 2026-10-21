<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function dashboard()
    {
        // 1. Hitung total Pemilih (DPT)
        $totalDpt = DB::table('pemilih')->count();
        
        // 2. Hitung partisipasi (Sudah milih vs Belum)
        $sudahMilih = DB::table('pemilih')->where('status_memilih', 1)->count();
        $belumMilih = DB::table('pemilih')->where('status_memilih', 0)->count();

        // 3. Hitung Demografi Gender (Hanya yang sudah nyoblos)
        $pemilihLaki = DB::table('pemilih')->where('status_memilih', 1)->where('jenis_kelamin', 'L')->count();
        $pemilihPerempuan = DB::table('pemilih')->where('status_memilih', 1)->where('jenis_kelamin', 'P')->count();

        // 4. Hitung Suara Sah & Tidak Sah
        $suaraSah = DB::table('suara')->whereNotNull('kandidat_id')->count();
        $suaraTidakSah = DB::table('suara')->whereNull('kandidat_id')->count();

        // 5. Rekapitulasi Suara Kandidat (Quick Count)
        $kandidat = DB::table('kandidat')
            ->leftJoin('suara', 'kandidat.id', '=', 'suara.kandidat_id')
            ->select('kandidat.id', 'kandidat.no_urut', 'kandidat.nama', DB::raw('count(suara.id) as total_suara'))
            ->groupBy('kandidat.id', 'kandidat.no_urut', 'kandidat.nama')
            ->orderBy('kandidat.no_urut', 'asc')
            ->get();

        // Kirim semua data ini ke halaman dashboard
        return view('admin.dashboard', compact(
            'totalDpt', 'sudahMilih', 'belumMilih', 
            'pemilihLaki', 'pemilihPerempuan', 
            'suaraSah', 'suaraTidakSah', 'kandidat'
        ));
    }
}