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
Schema::create('suara', function (Blueprint $table) {
        $table->id();
        // Nyambungin suara ini buat kandidat yang mana
        $table->foreignId('kandidat_id')->nullable()->constrained('kandidat'); 
        $table->timestamps(); // Cuma nyatet jam berapa suara masuk
        
        // CONTOH NYATA: Ini persis kayak kotak suara asli. 
        // Kita tau di dalam kotak ada kertas suara buat kandidat X, 
        // tapi di kertas suaranya nggak ditulis nama siapa yang nyoblos.
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suara');
    }
};
