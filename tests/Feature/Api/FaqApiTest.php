<?php

namespace Tests\Feature\Api;

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
}
