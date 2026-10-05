<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilamentAssetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_filament_assets_are_shipped_and_the_login_page_loads_them(): void
    {
        $this->assertFileExists(public_path('css/filament/filament/app.css'));
        $this->assertFileExists(public_path('js/filament/filament/app.js'));
        $this->assertFileExists(public_path('fonts/filament/filament/inter/index.css'));

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('/css/filament/filament/app.css', false)
            ->assertSee('/images/anihow-mark.png', false);
    }
}
