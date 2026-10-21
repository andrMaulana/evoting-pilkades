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
Schema::create('kandidat', function (Blueprint $table) {
        $table->id(); // Bikin ID otomatis
        $table->integer('no_urut')->unique(); // No urut nggak boleh ada yang sama
        $table->string('nama');
        $table->string('foto')->nullable(); // Boleh kosong dulu kalau belum ada foto
        $table->timestamps(); // Otomatis nyatet waktu data dibuat/diupdate
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kandidat');
    }
};
