<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\FotoKandidat;

class KandidatController extends Controller
{
    public function index()
    {
        // Ambil data kandidat urut berdasarkan nomor urutnya
        $kandidat = DB::table('kandidat')->orderBy('no_urut', 'asc')->get()
            ->map(function ($k) {
                $k->foto_url = FotoKandidat::url($k->foto);
                return $k;
            });
        return view('admin.kandidat', compact('kandidat'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'no_urut' => 'required|numeric|unique:kandidat,no_urut',
            'nama'    => 'required|string|max:255',
            'foto'    => 'nullable|image|mimes:jpeg,png,jpg|max:2048', // Validasi file gambar maksimal 2MB
        ]);

        $nama_file_foto = null;
        if ($request->hasFile('foto')) {
            // Disk 'public' = storage/app/public. Nama file diacak otomatis (aman dari nama aneh / bentrok).
            $nama_file_foto = basename($request->file('foto')->store('kandidat', 'public'));
        }

        DB::table('kandidat')->insert([
            'no_urut'    => $request->no_urut,
            'nama'       => strtoupper($request->nama),
            'foto'       => $nama_file_foto,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Kandidat baru beserta foto berhasil ditambahkan!');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'no_urut' => 'required|numeric|unique:kandidat,no_urut,' . $id,
            'nama'    => 'required|string|max:255',
            'foto'    => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        // Ambil data kandidat lama untuk ngecek foto lamanya
        $kandidatLama = DB::table('kandidat')->where('id', $id)->first();
        $nama_file_foto = $kandidatLama->foto;

        // Kalau ada file foto baru yang di-upload
        if ($request->hasFile('foto')) {
            $nama_file_foto = basename($request->file('foto')->store('kandidat', 'public'));

            // Hapus foto lama dari gudang (kalau ada) biar nggak menuhin memori
            FotoKandidat::hapus($kandidatLama->foto);
        }

        DB::table('kandidat')->where('id', $id)->update([
            'no_urut'    => $request->no_urut,
            'nama'       => strtoupper($request->nama),
            'foto'       => $nama_file_foto,
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Data & foto kandidat berhasil diperbarui!');
    }

    public function destroy($id)
    {
        $cekSuara = DB::table('suara')->where('kandidat_id', $id)->count();
        if ($cekSuara > 0) {
            return back()->withErrors(['error' => 'Gagal: Kandidat tidak bisa dihapus karena sudah memiliki suara!']);
        }

        $kandidatLama = DB::table('kandidat')->where('id', $id)->first();
        // Hapus juga fotonya saat kandidat dihapus
        FotoKandidat::hapus($kandidatLama->foto);

        DB::table('kandidat')->where('id', $id)->delete();
        return back()->with('success', 'Kandidat beserta fotonya berhasil dihapus!');
    }
}