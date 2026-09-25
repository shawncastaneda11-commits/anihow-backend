<?php

namespace Tests\Feature;

use App\Actions\Reports\DismissReportAction;
use App\Actions\Reports\ResolveReportAction;
use App\Enums\ListingStatus;
use App\Enums\NotificationType;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Enums\Role;
use App\Filament\Resources\Reports\ReportResource;
use App\Models\Farm;
use App\Models\InAppNotification;
use App\Models\Listing;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class ReportCmsTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_super_admin_resolve_with_takedown_notifies_the_seller_and_the_reporter(): void
    {
        $admin = $this->staff(Role::SuperAdmin);
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer, ['title' => 'Morning crate']);
        $buyer = $this->buyer();
        $report = $this->openReport($buyer, $listing, ReportReason::ProhibitedItem);

        app(ResolveReportAction::class)->handle(
            $report,
            $admin,
            'Removed from the marketplace.',
            true,
            'Item not allowed to be sold.',
        );

        $this->assertSame(ListingStatus::TakenDown, $listing->fresh()->status);
        $this->assertFalse($listing->fresh()->is_active);
        $this->assertSame(ReportStatus::Resolved, $report->fresh()->status);
        $this->assertSame(1, $this->notices($farmer->id, NotificationType::ListingTakenDown));
        $this->assertSame(1, $this->notices($buyer->id, NotificationType::ReportResolved));
        $this->assertSame(0, $this->notices($buyer->id, NotificationType::ListingTakenDown));

        $body = InAppNotification::query()
            ->where('user_id', $buyer->id)
            ->where('type', NotificationType::ReportResolved)
            ->value('body');

        $this->assertSame('Your report was reviewed and resolved.', $body);
        $this->assertStringNotContainsString('Removed from the marketplace.', (string) $body);
    }

    public function test_super_admin_resolve_with_review_removal(): void
    {
        $admin = $this->staff(Role::SuperAdmin);
        $farmer = $this->farmer();
        $buyer = $this->buyer();
        $listing = $this->listingFor($farmer, ['price_per_unit' => 30, 'quantity_available' => 20]);
        $order = $this->completeOrder($farmer, $this->placeOrder($buyer, $listing, 1), 30);
        $review = Review::query()->create([
            'order_id' => $order->id,
            'buyer_id' => $buyer->id,
            'farmer_seller_id' => $farmer->id,
            'rating' => 1,
            'comment' => 'Abusive note.',
            'is_removed' => false,
        ]);
        $report = $this->openReport($farmer, $review, ReportReason::OffensiveContent);

        app(ResolveReportAction::class)->handle(
            $report,
            $admin,
            'Review removed.',
            true,
            'Offensive language.',
        );

        $this->assertTrue($review->fresh()->is_removed);
        $this->assertSame(ReportStatus::Resolved, $report->fresh()->status);
        $this->assertSame(1, $this->notices($farmer->id, NotificationType::ReportResolved));
        $this->assertSame(0, $this->notices($buyer->id, NotificationType::ReportResolved));
    }

    public function test_dismiss_notifies_the_reporter_with_a_fixed_message(): void
    {
        $admin = $this->staff(Role::SuperAdmin);
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer);
        $buyer = $this->buyer();
        $report = $this->openReport($buyer, $listing, ReportReason::SpamOrFake);

        app(DismissReportAction::class)->handle($report, $admin);

        $this->assertSame(ReportStatus::Dismissed, $report->fresh()->status);
        $this->assertSame(ListingStatus::Published, $listing->fresh()->status);
        $this->assertSame(1, $this->notices($buyer->id, NotificationType::ReportDismissed));
        $this->assertSame(
            'Your report was reviewed and dismissed.',
            InAppNotification::query()
                ->where('user_id', $buyer->id)
                ->where('type', NotificationType::ReportDismissed)
                ->value('body'),
        );
    }

    public function test_content_editor_cannot_open_the_reports_resource(): void
    {
        $admin = $this->staff(Role::SuperAdmin);
        $editor = $this->staff(Role::ContentEditor, Farm::factory()->create());

        $this->actingAs($admin)
            ->get('/admin/reports')
            ->assertOk();

        $this->actingAs($editor);

        $this->assertFalse(ReportResource::canAccess());

        $this->get('/admin/reports')
            ->assertForbidden();
    }

    public function test_resolving_or_dismissing_a_closed_report_throws_and_does_not_take_down(): void
    {
        $admin = $this->staff(Role::SuperAdmin);
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer, ['title' => 'Morning crate']);
        $buyer = $this->buyer();

        $alreadyResolved = $this->openReport($buyer, $listing, ReportReason::ProhibitedItem);
        $alreadyResolved->update([
            'status' => ReportStatus::Resolved,
            'resolved_by' => $admin->id,
            'resolved_at' => now(),
        ]);

        $this->assertClosedReportIsGuarded(
            fn () => app(ResolveReportAction::class)->handle(
                $alreadyResolved,
                $admin,
                'Again.',
                true,
                'Take it down.',
            ),
        );
        $this->assertClosedReportIsGuarded(
            fn () => app(DismissReportAction::class)->handle($alreadyResolved, $admin),
        );

        $alreadyDismissed = $this->openReport($buyer, $listing, ReportReason::SpamOrFake);
        $alreadyDismissed->update([
            'status' => ReportStatus::Dismissed,
            'resolved_by' => $admin->id,
            'resolved_at' => now(),
        ]);

        $this->assertClosedReportIsGuarded(
            fn () => app(ResolveReportAction::class)->handle(
                $alreadyDismissed,
                $admin,
                'Resolve anyway.',
                true,
                'Take it down.',
            ),
        );
        $this->assertClosedReportIsGuarded(
            fn () => app(DismissReportAction::class)->handle($alreadyDismissed, $admin),
        );

        $this->assertSame(ListingStatus::Published, $listing->fresh()->status);
        $this->assertSame(0, $this->notices($buyer->id, NotificationType::ReportResolved));
        $this->assertSame(0, $this->notices($buyer->id, NotificationType::ReportDismissed));
        $this->assertSame(0, $this->notices($farmer->id, NotificationType::ListingTakenDown));
    }

    /**
     * @param  callable(): mixed  $action
     */
    private function assertClosedReportIsGuarded(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected ValidationException for a closed report.');
        } catch (ValidationException $exception) {
            $this->assertSame(['This report was already closed.'], $exception->errors()['status']);
        }
    }

    private function openReport(User $reporter, Listing|Review $target, ReportReason $reason): Report
    {
        return Report::factory()->create([
            'reporter_id' => $reporter->id,
            'reportable_id' => $target->id,
            'reportable_type' => $target::class,
            'reason' => $reason,
            'status' => ReportStatus::Open,
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
