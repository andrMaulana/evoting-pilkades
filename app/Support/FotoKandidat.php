<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Satu tempat untuk urusan foto kandidat, supaya halaman voting dan halaman admin
 * memakai aturan path yang sama.
 *
 *  - Foto upload admin   : disimpan di storage/app/public/kandidat, kolom `foto` = nama file saja
 *  - Foto bawaan seeder  : kolom `foto` berawalan "img/" (file ada di public/img), tidak ikut dihapus
 */
class FotoKandidat
{
    /** URL foto untuk <img>, atau null kalau kandidat belum punya foto. */
    public static function url(?string $foto): ?string
    {
        if (!$foto) {
            return null;
        }

        if (str_starts_with($foto, 'img/')) {
            return asset($foto);
        }

        return asset('storage/kandidat/' . rawurlencode($foto));
    }

    /** Hapus file foto upload (foto bawaan di public/img tidak disentuh). */
    public static function hapus(?string $foto): void
    {
        if ($foto && !str_starts_with($foto, 'img/')) {
            Storage::disk('public')->delete('kandidat/' . $foto);
        }
    }
}
