<?php

namespace App\Http\Controllers;

use App\Support\Pengaturan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    // Selang polling dashboard (milidetik). Dipakai oleh JavaScript di halaman dashboard.
    private const SELANG_POLLING_MS = 3000;

    public function dashboard()
    {
        return view('admin.dashboard', [
            'data'      => $this->statistik(),
            'selangMs'  => self::SELANG_POLLING_MS,
        ]);
    }

    // Data terbaru untuk dashboard (dipanggil berkala oleh browser, tanpa reload halaman)
    public function dashboardData(): JsonResponse
    {
        return $this->json($this->statistik());
    }

    // Sakelar "Sembunyikan perolehan kandidat". Disimpan di server, berlaku untuk semua admin.
    public function sembunyikanPerolehan(Request $request): JsonResponse
    {
        $request->validate(['sembunyikan' => 'required|boolean']);

        Pengaturan::simpan('sembunyikan_perolehan', $request->boolean('sembunyikan') ? '1' : '0');

        return $this->json($this->statistik());
    }

    private function json(array $data): JsonResponse
    {
        // Data live: jangan sampai disimpan cache browser/proxy
        return response()->json($data)->header('Cache-Control', 'no-store, private');
    }

    /** Semua angka dashboard. Satu sumber untuk tampilan awal dan data realtime. */
    private function statistik(): array
    {
        // 1-3. Pemilih: total, partisipasi, dan gender (yang sudah memilih), dalam satu query
        $p = DB::table('pemilih')->selectRaw("
            COUNT(*) AS total,
            COALESCE(SUM(CASE WHEN status_memilih = 1 THEN 1 ELSE 0 END), 0) AS sudah,
            COALESCE(SUM(CASE WHEN status_memilih = 0 THEN 1 ELSE 0 END), 0) AS belum,
            COALESCE(SUM(CASE WHEN status_memilih = 1 AND jenis_kelamin = 'L' THEN 1 ELSE 0 END), 0) AS laki,
            COALESCE(SUM(CASE WHEN status_memilih = 1 AND jenis_kelamin = 'P' THEN 1 ELSE 0 END), 0) AS perempuan
        ")->first();

        // 4. Suara sah (ada kandidat) dan tidak sah (kandidat_id kosong)
        $s = DB::table('suara')->selectRaw('COUNT(kandidat_id) AS sah, COUNT(*) - COUNT(kandidat_id) AS batal')->first();
        $suaraSah = (int) $s->sah;

        // 5. Perolehan kandidat. Kalau sedang disembunyikan, angkanya TIDAK dihitung dan TIDAK dikirim sama sekali.
        $disembunyikan = Pengaturan::perolehanDisembunyikan();

        if ($disembunyikan) {
            $kandidat = DB::table('kandidat')->orderBy('no_urut')->get(['id', 'no_urut', 'nama'])
                ->map(fn ($k) => ['id' => (int) $k->id, 'no_urut' => (int) $k->no_urut, 'nama' => $k->nama])
                ->all();
        } else {
            $kandidat = DB::table('kandidat')
                ->leftJoin('suara', 'kandidat.id', '=', 'suara.kandidat_id')
                ->select('kandidat.id', 'kandidat.no_urut', 'kandidat.nama', DB::raw('count(suara.id) as total_suara'))
                ->groupBy('kandidat.id', 'kandidat.no_urut', 'kandidat.nama')
                ->orderBy('kandidat.no_urut', 'asc')
                ->get()
                ->map(fn ($k) => [
                    'id'          => (int) $k->id,
                    'no_urut'     => (int) $k->no_urut,
                    'nama'        => $k->nama,
                    'total_suara' => (int) $k->total_suara,
                    // persen dari suara sah (cegah dibagi nol)
                    'persen'      => $suaraSah > 0 ? round(($k->total_suara / $suaraSah) * 100, 1) : 0,
                ])
                ->all();
        }

        return [
            'totalDpt'         => (int) $p->total,
            'sudahMilih'       => (int) $p->sudah,
            'belumMilih'       => (int) $p->belum,
            'pemilihLaki'      => (int) $p->laki,
            'pemilihPerempuan' => (int) $p->perempuan,
            'suaraSah'         => $suaraSah,
            'suaraTidakSah'    => (int) $s->batal,
            'tersembunyi'      => $disembunyikan,
            'kandidat'         => $kandidat,
            'diperbarui'       => now()->format('H:i:s'),
        ];
    }
}
