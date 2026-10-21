<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
Schema::create('pemilih', function (Blueprint $table) {
// ID ini nanti isinya kode unik dari QR Code lu
        $table->string('id')->primary(); 
        
        // NIK pakai string dan dibatasi 16 karakter. 
        // Kenapa string? Karena NIK bisa diawali angka 0, kalau pakai integer nanti 0 di depannya hilang.
        // unique() biar nggak ada NIK yang terdaftar 2 kali.
        $table->string('nik', 16)->unique(); 
        
        $table->string('nama');
        
        // Dua baris baru untuk analitik Dashboard
        $table->enum('jenis_kelamin', ['L', 'P'])->nullable(); 
        $table->integer('umur')->nullable();

        // No urut DPT, TPS, dan Bilik kita pakai integer biar nanti gampang divalidasi (misal: if tps == 8)
        $table->integer('no_urut_dpt'); 
        $table->integer('no_tps');   // Nanti pas input data, cukup masukin angkanya aja, misal: 8
        $table->integer('no_bilik'); // Cukup masukin angkanya aja, misal: 1
        
        // Status awal 0 (false) yang artinya belum milih
        $table->boolean('status_memilih')->default(false); 
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pemilih');
    }
};
