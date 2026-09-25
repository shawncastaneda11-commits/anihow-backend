<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\FaqEntry;
use App\Models\Farm;
use Database\Seeders\FaqEntrySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class FaqApiTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(FaqEntrySeeder::class);
    }

    public function test_buyer_gets_role_chips_and_can_match_a_question(): void
    {
        $buyer = $this->buyer();

        $this->asUser($buyer)
            ->getJson('/api/faq')
            ->assertOk()
            ->assertJsonFragment(['id' => 'tawad_buyer'])
            ->assertJsonFragment(['id' => 'otp_verify'])
            ->assertJsonMissing(['id' => 'crop_care_pointer']);

        $this->asUser($buyer)
            ->postJson('/api/faq/ask', ['question' => 'What is tawad?'])
            ->assertOk()
            ->assertJsonPath('data.matched_id', 'tawad_buyer')
            ->assertJsonPath(
                'data.answer',
                fn (string $answer): bool => str_contains($answer, 'peso discount'),
            );
    }

    public function test_farmer_gets_crop_care_pointer_without_article_prose(): void
    {
        $farmer = $this->farmer();

        $this->asUser($farmer)
            ->getJson('/api/faq')
            ->assertOk()
            ->assertJsonFragment(['id' => 'crop_care_pointer'])
            ->assertJsonFragment(['id' => 'walk_in'])
            ->assertJsonMissing(['id' => 'otp_verify']);

        $response = $this->asUser($farmer)
            ->postJson('/api/faq/ask', ['question' => 'Where is crop care?'])
            ->assertOk()
            ->assertJsonPath('data.matched_id', 'crop_care_pointer');

        $answer = $response->json('data.answer');

        $this->assertStringContainsString('Crop care tab', $answer);
        $this->assertStringNotContainsString('Pest Management', $answer);
        $this->assertStringNotContainsString('kamatis leaves', $answer);
    }

    public function test_buyer_crop_care_query_does_not_return_article_prose(): void
    {
        $buyer = $this->buyer();

        $response = $this->asUser($buyer)
            ->postJson('/api/faq/ask', ['question' => 'Tell me about pest management for kamatis leaves'])
            ->assertOk();

        $this->assertNull($response->json('data.matched_id'));
        $this->assertStringContainsString('Chat with stall', $response->json('data.answer'));
        $this->assertStringNotContainsString('spray', strtolower($response->json('data.answer')));
    }

    public function test_unknown_query_returns_fallback_and_suggestions(): void
    {
        $buyer = $this->buyer();

        $this->asUser($buyer)
            ->postJson('/api/faq/ask', ['question' => 'zzzz not a real topic'])
            ->assertOk()
            ->assertJsonPath('data.matched_id', null)
            ->assertJsonPath(
                'data.answer',
                'Tap a question below. For one order, open the order and tap Chat with stall.',
            )
            ->assertJsonPath('data.suggestions.0.id', fn ($id) => is_string($id) && $id !== '');
    }

    public function test_guest_cannot_use_faq(): void
    {
        $this->getJson('/api/faq')->assertUnauthorized();
        $this->postJson('/api/faq/ask', ['question' => 'What is tawad?'])->assertUnauthorized();
    }

    public function test_filipino_accept_language_returns_tagalog_answers(): void
    {
        $buyer = $this->buyer();

        $this->asUser($buyer)
            ->withHeaders(['Accept-Language' => 'fil'])
            ->getJson('/api/faq')
            ->assertOk()
            ->assertJsonFragment(['label' => 'Ano ang tawad?']);

        $this->asUser($buyer)
            ->withHeaders(['Accept-Language' => 'fil'])
            ->postJson('/api/faq/ask', ['question' => 'Ano ang tawad?'])
            ->assertOk()
            ->assertJsonPath('data.matched_id', 'tawad_buyer')
            ->assertJsonPath(
                'data.answer',
                fn (string $answer): bool => str_contains($answer, 'diskwentong piso'),
            );
    }

    public function test_empty_question_is_rejected(): void
    {
        $buyer = $this->buyer();

        $this->asUser($buyer)
            ->postJson('/api/faq/ask', ['question' => '  '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('question');
    }

    public function test_farm_override_replaces_system_answer_for_that_farms_farmers_only(): void
    {
        $farmA = Farm::factory()->create(['name' => 'Farm A']);
        $farmB = Farm::factory()->create(['name' => 'Farm B']);
        $farmerA = $this->farmer([], $farmA);
        $farmerB = $this->farmer([], $farmB);

        FaqEntry::factory()
            ->forFarm($farmA)
            ->forIntent('walk_in')
            ->forRoles([Role::FarmerSeller->value])
            ->create([
                'label' => 'What is a walk-in sale?',
                'label_fil' => 'Ano ang walk-in sale?',
                'keywords' => ['walk-in', 'walk in', 'walkin'],
                'answer' => 'Farm A walk-in: record it at the shed.',
                'answer_fil' => 'Farm A walk-in: i-record sa shed.',
            ]);

        $this->asUser($farmerA)
            ->postJson('/api/faq/ask', ['question' => 'What is a walk-in sale?'])
            ->assertOk()
            ->assertJsonPath('data.matched_id', 'walk_in')
            ->assertJsonPath(
                'data.answer',
                fn (string $answer): bool => str_contains($answer, 'Farm A walk-in'),
            );

        $this->asUser($farmerB)
            ->postJson('/api/faq/ask', ['question' => 'What is a walk-in sale?'])
            ->assertOk()
            ->assertJsonPath('data.matched_id', 'walk_in')
            ->assertJsonPath(
                'data.answer',
                fn (string $answer): bool => str_contains($answer, 'Record walk-in sale')
                    && ! str_contains($answer, 'Farm A walk-in'),
            );
    }

    public function test_inactive_rows_are_ignored(): void
    {
        $farmer = $this->farmer();

        FaqEntry::query()
            ->whereNull('farm_id')
            ->where('intent_key', 'walk_in')
            ->update(['is_active' => false]);

        $this->asUser($farmer)
            ->getJson('/api/faq')
            ->assertOk()
            ->assertJsonMissing(['id' => 'walk_in']);

        FaqEntry::factory()
            ->forFarm($farmer->farm)
            ->forIntent('walk_in')
            ->forRoles([Role::FarmerSeller->value])
            ->inactive()
            ->create([
                'label' => 'What is a walk-in sale?',
                'label_fil' => 'Ano ang walk-in sale?',
                'keywords' => ['walk-in', 'walk in'],
                'answer' => 'Inactive override must not show.',
                'answer_fil' => 'Hindi dapat lumabas.',
            ]);

        $this->asUser($farmer)
            ->postJson('/api/faq/ask', ['question' => 'What is a walk-in sale?'])
            ->assertOk()
            ->assertJsonPath('data.matched_id', fn ($id) => $id !== 'walk_in')
            ->assertJsonPath(
                'data.answer',
                fn (string $answer): bool => ! str_contains($answer, 'Inactive override'),
            );
    }
}
