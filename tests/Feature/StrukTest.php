<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StrukTest extends TestCase
{
    use RefreshDatabase;

    public function test_rute_cetak_struk_lama_sudah_tidak_ada(): void
    {
        // Dulu: /cetak-struk?id=NIK&kandidat=N bisa dibuka siapa saja dan membawa NIK di URL
        $this->get('/cetak-struk?id=3215010101900001&kandidat=2')->assertNotFound();
    }

    public function test_halaman_voting_membuat_struk_di_browser_tanpa_url_struk(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('id="struk-print"', false)
            ->assertSee('qrcode-generator', false)
            ->assertSee('jspdf', false)
            ->assertDontSee('cetak-struk', false);
    }
}
