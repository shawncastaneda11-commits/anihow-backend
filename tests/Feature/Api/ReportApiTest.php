<?php

namespace Tests\Feature\Api;

use App\Enums\ListingStatus;
use App\Enums\NotificationType;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Enums\Role;
use App\Models\Farm;
use App\Models\InAppNotification;
use App\Models\Listing;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class ReportApiTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_buyer_reports_a_published_listing_and_notifies_every_super_admin(): void
    {
        $adminA = $this->staff(Role::SuperAdmin);
        $adminB = $this->staff(Role::SuperAdmin);
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer, ['title' => 'Morning crate']);
        $buyer = $this->buyer();

        $response = $this->asUser($buyer)->postJson('/api/reports', [
            'target_type' => 'listing',
            'target_id' => $listing->id,
            'reason' => ReportReason::WrongOrMisleading->value,
            'details' => 'The photos do not match the crate.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.target_type', 'listing')
            ->assertJsonPath('data.reason', ReportReason::WrongOrMisleading->value)
            ->assertJsonPath('data.status', ReportStatus::Open->value)
            ->assertJsonMissingPath('data.reporter_id')
            ->assertJsonMissingPath('data.reporter');

        $this->assertDatabaseHas('reports', [
            'reporter_id' => $buyer->id,
            'reportable_id' => $listing->id,
            'reportable_type' => Listing::class,
            'reason' => ReportReason::WrongOrMisleading->value,
            'status' => ReportStatus::Open->value,
        ]);

        $this->assertSame(1, $this->notices($adminA->id, NotificationType::ReportSubmitted));
        $this->assertSame(1, $this->notices($adminB->id, NotificationType::ReportSubmitted));
        $this->assertSame(0, $this->notices($farmer->id, NotificationType::ReportSubmitted));
        $this->assertSame(0, $this->notices($buyer->id, NotificationType::ReportSubmitted));
    }

    public function test_farmer_seller_may_report_a_review_only_on_their_own_shop(): void
    {
        $this->staff(Role::SuperAdmin);
        $owner = $this->farmer();
        $otherSeller = $this->farmer();
        $buyer = $this->buyer();

        $ownReview = $this->reviewOnShop($owner, $buyer, ['comment' => 'Sour tomatoes.']);
        $otherReview = $this->reviewOnShop($otherSeller, $buyer);

        $this->asUser($owner)->postJson('/api/reports', [
            'target_type' => 'review',
            'target_id' => $ownReview->id,
            'reason' => ReportReason::OffensiveContent->value,
        ])
            ->assertCreated()
            ->assertJsonPath('data.target_type', 'review')
            ->assertJsonPath('data.status', ReportStatus::Open->value);

        $this->asUser($owner)->postJson('/api/reports', [
            'target_type' => 'review',
            'target_id' => $otherReview->id,
            'reason' => ReportReason::OffensiveContent->value,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('target_id');
    }

    public function test_nobody_may_report_their_own_listing_or_review(): void
    {
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer, ['price_per_unit' => 30, 'quantity_available' => 20]);
        $buyer = $this->buyer();
        $review = $this->reviewOnShop($farmer, $buyer, ['listing' => $listing]);

        $this->asUser($farmer)->postJson('/api/reports', [
            'target_type' => 'listing',
            'target_id' => $listing->id,
            'reason' => ReportReason::SpamOrFake->value,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('target_id');

        $this->asUser($buyer)->postJson('/api/reports', [
            'target_type' => 'review',
            'target_id' => $review->id,
            'reason' => ReportReason::SpamOrFake->value,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('target_id');
    }

    public function test_taken_down_listing_removed_review_and_missing_target_are_refused(): void
    {
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer);
        $listing->forceFill(['status' => ListingStatus::TakenDown])->save();

        $buyer = $this->buyer();
        $review = $this->reviewOnShop($farmer, $buyer, ['is_removed' => true]);
        $reporter = $this->buyer();

        $this->asUser($reporter)->postJson('/api/reports', [
            'target_type' => 'listing',
            'target_id' => $listing->id,
            'reason' => ReportReason::ProhibitedItem->value,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('target_id');

        $this->asUser($reporter)->postJson('/api/reports', [
            'target_type' => 'review',
            'target_id' => $review->id,
            'reason' => ReportReason::ProhibitedItem->value,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('target_id');

        $this->asUser($reporter)->postJson('/api/reports', [
            'target_type' => 'listing',
            'target_id' => 999_999,
            'reason' => ReportReason::Other->value,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('target_id');
    }

    public function test_duplicate_open_report_is_refused_until_the_first_is_dismissed(): void
    {
        $this->staff(Role::SuperAdmin);
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer);
        $buyer = $this->buyer();
        $payload = [
            'target_type' => 'listing',
            'target_id' => $listing->id,
            'reason' => ReportReason::SpamOrFake->value,
        ];

        $this->asUser($buyer)->postJson('/api/reports', $payload)->assertCreated();

        $this->asUser($buyer)->postJson('/api/reports', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('target_id');

        $report = Report::query()->firstOrFail();
        $report->update(['status' => ReportStatus::Dismissed]);

        $this->asUser($buyer)->postJson('/api/reports', $payload)->assertCreated();

        $this->assertSame(2, Report::query()->where('reporter_id', $buyer->id)->count());
    }

    public function test_content_editor_and_super_admin_cannot_submit_reports_and_guests_are_unauthenticated(): void
    {
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer);
        $payload = [
            'target_type' => 'listing',
            'target_id' => $listing->id,
            'reason' => ReportReason::Other->value,
        ];

        $this->postJson('/api/reports', $payload)->assertUnauthorized();

        $this->asUser($this->staff(Role::ContentEditor, Farm::factory()->create()))
            ->postJson('/api/reports', $payload)
            ->assertForbidden();

        $this->asUser($this->staff(Role::SuperAdmin))
            ->postJson('/api/reports', $payload)
            ->assertForbidden();
    }

    public function test_own_data_export_contains_reports_submitted_without_owner_names(): void
    {
        $this->staff(Role::SuperAdmin);
        $farmer = $this->farmer(['name' => 'Secret Seller', 'shop_name' => 'Hidden Stall']);
        $listing = $this->listingFor($farmer, ['title' => 'Kamatis']);
        $buyer = $this->buyer();

        $this->asUser($buyer)->postJson('/api/reports', [
            'target_type' => 'listing',
            'target_id' => $listing->id,
            'reason' => ReportReason::Other->value,
            'details' => 'Looks off.',
        ])->assertCreated();

        $response = $this->asUser($buyer)->get('/api/auth/user/export');
        $response->assertOk();

        $payload = json_decode($response->streamedContent(), true);

        $this->assertCount(1, $payload['reports_submitted']);
        $this->assertSame('listing', $payload['reports_submitted'][0]['target_type']);
        $this->assertSame(ReportReason::Other->value, $payload['reports_submitted'][0]['reason']);
        $this->assertSame(ReportStatus::Open->value, $payload['reports_submitted'][0]['status']);
        $this->assertArrayHasKey('created_at', $payload['reports_submitted'][0]);
        $this->assertArrayNotHasKey('seller_name', $payload['reports_submitted'][0]);
        $this->assertStringNotContainsString('Secret Seller', json_encode($payload['reports_submitted']));
        $this->assertStringNotContainsString('Hidden Stall', json_encode($payload['reports_submitted']));
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
            'rating' => 5,
            'comment' => $attributes['comment'] ?? 'Ok.',
            'is_removed' => $attributes['is_removed'] ?? false,
        ]);
    }

    private function staff(Role $role, ?Farm $farm = null): User
    {
        $user = User::factory()->create(['farm_id' => $farm?->id]);
        $user->syncRoles($role);

        return $user;
    }

    private function notices(int $userId, NotificationType $type): int
    {
        return InAppNotification::query()
            ->where('user_id', $userId)
            ->where('type', $type)
            ->count();
    }
}
