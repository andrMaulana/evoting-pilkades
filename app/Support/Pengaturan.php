<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pengaturan aplikasi sederhana (kunci -> nilai) yang disimpan di tabel `pengaturan`,
 * jadi berlaku untuk semua admin dan tidak hilang saat cache dibersihkan.
 */
class Pengaturan
{
    private static ?bool $tabelAda = null;

    private static function tabelAda(): bool
    {
        return self::$tabelAda ??= Schema::hasTable('pengaturan');
    }

    /** Dipakai tes untuk mengulang pemeriksaan keberadaan tabel. */
    public static function lupakanCache(): void
    {
        self::$tabelAda = null;
    }

    public static function ambil(string $kunci, ?string $bawaan = null): ?string
    {
        // Kalau migrasinya belum dijalankan, jangan sampai halaman error: pakai nilai bawaan.
        if (!self::tabelAda()) {
            return $bawaan;
        }

        return DB::table('pengaturan')->where('kunci', $kunci)->value('nilai') ?? $bawaan;
    }

    public static function simpan(string $kunci, string $nilai): void
    {
        DB::table('pengaturan')->updateOrInsert(
            ['kunci' => $kunci],
            ['nilai' => $nilai, 'updated_at' => now()]
        );
    }

    /** Nomor TPS yang dikunci untuk layar voting ini, atau null kalau semua TPS boleh. */
    public static function tpsKiosk(): ?int
    {
        $tps = (int) self::ambil('tps_kiosk', '0');

        return $tps > 0 ? $tps : null;
    }

    public static function setTpsKiosk(?int $tps): void
    {
        self::simpan('tps_kiosk', $tps === null ? '0' : (string) $tps);
    }

    /** Apakah perolehan suara per kandidat sedang disembunyikan di dashboard. */
    public static function perolehanDisembunyikan(): bool
    {
        return self::ambil('sembunyikan_perolehan', '0') === '1';
    }
}
