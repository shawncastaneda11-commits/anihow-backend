<?php

namespace Tests\Feature;

use App\Actions\Privacy\AnonymizeUserAction;
use App\Enums\Role;
use App\Models\Farm;
use App\Models\Review;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class FarmerShopReviewsTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_farmer_seller_sees_only_own_visible_reviews_newest_first(): void
    {
        $owner = $this->farmer();
        $otherSeller = $this->farmer();
        $buyer = $this->buyer();

        $older = $this->reviewOnShop($owner, $buyer, ['comment' => 'First.', 'rating' => 4]);
        $newer = $this->reviewOnShop($owner, $this->buyer(), ['comment' => 'Second.', 'rating' => 5]);
        $this->reviewOnShop($owner, $this->buyer(), ['comment' => 'Hidden.', 'rating' => 1, 'is_removed' => true]);
        $this->reviewOnShop($otherSeller, $buyer, ['comment' => 'Other stall.']);

        $this->asUser($owner)
            ->getJson('/api/farmer/shop/reviews')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.0.comment', 'Second.')
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonPath('data.1.comment', 'First.')
            ->assertJsonMissing(['comment' => 'Hidden.'])
            ->assertJsonMissing(['comment' => 'Other stall.']);
    }

    public function test_farmer_and_buyer_shop_stats_ignore_removed_reviews_and_match(): void
    {
        $farmer = $this->farmer();
        $buyer = $this->buyer();
        $this->reviewOnShop($farmer, $buyer, ['rating' => 5, 'comment' => 'Sariwa.']);
        $this->reviewOnShop($farmer, $this->buyer(), ['rating' => 1, 'comment' => 'Gone.', 'is_removed' => true]);

        $farmerReviews = $this->asUser($farmer)
            ->getJson('/api/farmer/shop/reviews')
            ->assertOk()
            ->assertJsonPath('reviews_count', 1)
            ->assertJsonPath('average_rating', '5.0')
            ->assertJsonCount(1, 'data');

        $farmerShop = $this->asUser($farmer)
            ->getJson('/api/farmer/shop')
            ->assertOk()
            ->assertJsonPath('data.reviews_count', 1)
            ->assertJsonPath('data.average_rating', '5.0');

        $buyerShop = $this->asUser($buyer)
            ->getJson("/api/buyer/shops/{$farmer->id}")
            ->assertOk()
            ->assertJsonPath('data.reviews_count', 1)
            ->assertJsonPath('data.average_rating', '5.0');

        $buyerReviews = $this->asUser($buyer)
            ->getJson("/api/buyer/shops/{$farmer->id}/reviews")
            ->assertOk()
            ->assertJsonPath('reviews_count', 1)
            ->assertJsonPath('average_rating', '5.0')
            ->assertJsonCount(1, 'data');

        $this->assertSame($farmerReviews->json('reviews_count'), $buyerReviews->json('reviews_count'));
        $this->assertSame($farmerReviews->json('average_rating'), $buyerReviews->json('average_rating'));
        $this->assertSame($farmerShop->json('data.reviews_count'), $buyerShop->json('data.reviews_count'));
        $this->assertSame($farmerShop->json('data.average_rating'), $buyerShop->json('data.average_rating'));
    }

    public function test_buyer_and_content_editor_cannot_read_farmer_shop_reviews(): void
    {
        $this->getJson('/api/farmer/shop/reviews')->assertUnauthorized();

        $this->asUser($this->buyer())
            ->getJson('/api/farmer/shop/reviews')
            ->assertForbidden();

        $editor = User::factory()->create(['farm_id' => Farm::factory()->create()->id]);
        $editor->syncRoles(Role::ContentEditor);

        $this->asUser($editor)
            ->getJson('/api/farmer/shop/reviews')
            ->assertForbidden();
    }

    public function test_a_review_by_an_anonymised_buyer_still_lists_their_anonymised_name(): void
    {
        $farmer = $this->farmer();
        $buyer = $this->buyer(['name' => 'Maria Buyer']);
        $this->reviewOnShop($farmer, $buyer, ['comment' => 'Sariwa.']);

        app(AnonymizeUserAction::class)->handle($buyer);

        $this->asUser($farmer)
            ->getJson('/api/farmer/shop/reviews')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.comment', 'Sariwa.')
            ->assertJsonPath('data.0.buyer_name', 'Deleted user');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function reviewOnShop(User $farmer, User $buyer, array $attributes = []): Review
    {
        $listing = $attributes['listing'] ?? $this->listingFor($farmer, [
            'price_per_unit' => 30,
            'quantity_available' => 20,
        ]);
        $order = $this->completeOrder($farmer, $this->placeOrder($buyer, $listing, 1), 30);

        return Review::query()->create([
            'order_id' => $order->id,
            'buyer_id' => $buyer->id,
            'farmer_seller_id' => $farmer->id,
            'rating' => $attributes['rating'] ?? 5,
            'comment' => $attributes['comment'] ?? 'Ok.',
            'is_removed' => $attributes['is_removed'] ?? false,
        ]);
    }
}
