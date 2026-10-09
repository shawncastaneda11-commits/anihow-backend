<?php

namespace Tests\Feature;

use App\Models\Farm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerHelpTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_help_is_public_and_lists_only_active_farms(): void
    {
        config([
            'anihow.seller_help.email' => 'ict@lpu.edu.ph',
            'anihow.seller_help.office' => 'LPU ICTD',
            'anihow.seller_help.phone' => null,
            'anihow.auth.temporary_password_days' => 7,
        ]);

        $first = Farm::factory()->create([
            'name' => 'Apple Farm',
            'municipality' => 'General Trias',
            'contact_person' => 'Elena Ramos',
            'contact_number' => '09171234567',
        ]);
        Farm::factory()->create([
            'name' => 'Blank Farm',
            'municipality' => '',
            'contact_person' => '   ',
            'contact_number' => '',
        ]);
        Farm::factory()->inactive()->create(['name' => 'Closed Farm']);

        $response = $this->getJson('/api/seller-help')
            ->assertOk()
            ->assertJsonPath('data.contact.email', 'ict@lpu.edu.ph')
            ->assertJsonPath('data.contact.office', 'LPU ICTD')
            ->assertJsonPath('data.contact.phone', null)
            ->assertJsonPath('data.temporary_password_days', 7)
            ->assertJsonCount(2, 'data.farms')
            ->assertJsonPath('data.farms.0.id', $first->id)
            ->assertJsonPath('data.farms.0.name', 'Apple Farm');

        $farm = $response->json('data.farms.0');
        $this->assertSame(
            ['id', 'name', 'municipality', 'contact_person', 'contact_number'],
            array_keys($farm),
        );
        $this->assertSame('General Trias', $farm['municipality']);
        $this->assertSame('Elena Ramos', $farm['contact_person']);
        $this->assertSame('09171234567', $farm['contact_number']);

        $blank = $response->json('data.farms.1');
        $this->assertNull($blank['municipality']);
        $this->assertNull($blank['contact_person']);
        $this->assertNull($blank['contact_number']);
        $this->assertStringNotContainsString('Closed Farm', $response->getContent());
    }

    public function test_phone_and_temporary_password_days_come_from_config(): void
    {
        config([
            'anihow.seller_help.phone' => '09170001111',
            'anihow.auth.temporary_password_days' => 12,
        ]);

        $this->getJson('/api/seller-help')
            ->assertOk()
            ->assertJsonPath('data.contact.phone', '09170001111')
            ->assertJsonPath('data.temporary_password_days', 12);
    }
}
