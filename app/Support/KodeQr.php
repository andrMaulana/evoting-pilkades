<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Kode QR pribadi untuk kartu/undangan pemilih.
 *
 * Kode ini acak dan TIDAK diturunkan dari NIK, jadi orang yang tahu NIK tidak bisa
 * membuat QR yang valid. 12 karakter dari 32 huruf/angka (tanpa I, O, 0, 1 yang mudah
 * tertukar) = sekitar 60 bit, tidak praktis ditebak lewat percobaan berulang.
 */
class KodeQr
{
    private const ALFABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // 32 karakter
    public const PANJANG = 12;

    /** Buat satu kode acak (pakai random_int, aman secara kriptografi). */
    public static function buat(): string
    {
        $kode = '';
        for ($i = 0; $i < self::PANJANG; $i++) {
            $kode .= self::ALFABET[random_int(0, 31)];
        }
        return $kode;
    }

    /** Buat kode yang belum dipakai pemilih mana pun di database. */
    public static function unikDiDb(): string
    {
        do {
            $kode = self::buat();
        } while (DB::table('pemilih')->where('kode_qr', $kode)->exists());

        return $kode;
    }

    /** Rapikan hasil scan: buang spasi, jadikan huruf besar. */
    public static function bersihkan(mixed $input): string
    {
        return strtoupper(trim((string) $input));
    }

    /** Apakah bentuknya sesuai kode kita (12 karakter dari alfabet di atas). */
    public static function valid(?string $kode): bool
    {
        return $kode !== null && preg_match('/^[A-HJ-NP-Z2-9]{12}$/', $kode) === 1;
    }
}
