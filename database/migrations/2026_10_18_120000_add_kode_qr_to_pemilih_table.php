<?php

use App\Support\KodeQr;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kolom kode QR acak, terpisah dari NIK. nullable dulu agar bisa diisi untuk data yang sudah ada.
        Schema::table('pemilih', function (Blueprint $table) {
            $table->string('kode_qr', 16)->nullable()->unique()->after('nik');
        });

        // Isi kode untuk semua pemilih yang sudah terdaftar
        $dipakai = [];
        DB::table('pemilih')->whereNull('kode_qr')->orderBy('id')->chunkById(500, function ($baris) use (&$dipakai) {
            foreach ($baris as $p) {
                do {
                    $kode = KodeQr::buat();
                } while (isset($dipakai[$kode]));
                $dipakai[$kode] = true;

                DB::table('pemilih')->where('id', $p->id)->update(['kode_qr' => $kode]);
            }
        }, 'id');
    }

    public function down(): void
    {
        Schema::table('pemilih', function (Blueprint $table) {
            $table->dropUnique(['kode_qr']);
        });
        Schema::table('pemilih', function (Blueprint $table) {
            $table->dropColumn('kode_qr');
        });
    }
};
