<?php

namespace App\Actions\Reports;

use App\Enums\ListingStatus;
use App\Enums\Permission;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\Listing;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use App\Support\InAppNotifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class SubmitReportAction
{
    public function __construct(private InAppNotifier $notifier) {}

    public function handle(
        User $reporter,
        string $targetType,
        int $targetId,
        ReportReason $reason,
        ?string $details = null,
    ): Report {
        $target = $this->resolveVisibleTarget($reporter, $targetType, $targetId);

        $this->guardAgainstDuplicateOpenReport($reporter, $target);

        $report = Report::query()->create([
            'reporter_id' => $reporter->id,
            'reportable_id' => $target->getKey(),
            'reportable_type' => $target->getMorphClass(),
            'reason' => $reason,
            'details' => $details,
            'status' => ReportStatus::Open,
        ]);

        User::query()
            ->permission(Permission::ResolveReports->value)
            ->whereKeyNot($reporter->id)
            ->each(fn (User $admin): mixed => $this->notifier->reportSubmitted($admin, $report));

        return $report;
    }

    private function resolveVisibleTarget(User $reporter, string $targetType, int $targetId): Model
    {
        if ($targetType === 'listing') {
            return $this->resolveListing($reporter, $targetId);
        }

        if ($targetType === 'review') {
            return $this->resolveReview($reporter, $targetId);
        }

        throw ValidationException::withMessages([
            'target_type' => 'You can only report a listing or a review.',
        ]);
    }

    private function resolveListing(User $reporter, int $targetId): Listing
    {
        $listing = Listing::query()->find($targetId);

        if ($listing === null || $listing->status !== ListingStatus::Published) {
            throw ValidationException::withMessages([
                'target_id' => 'That listing is not available to report.',
            ]);
        }

        if ($listing->farmer_seller_id === $reporter->id) {
            throw ValidationException::withMessages([
                'target_id' => 'You cannot report your own listing.',
            ]);
        }

        return $listing;
    }

    private function resolveReview(User $reporter, int $targetId): Review
    {
        $review = Review::query()->find($targetId);

        if ($review === null || $review->is_removed) {
            throw ValidationException::withMessages([
                'target_id' => 'That review is not available to report.',
            ]);
        }

        if ($review->buyer_id === $reporter->id) {
            throw ValidationException::withMessages([
                'target_id' => 'You cannot report your own review.',
            ]);
        }

        if ($reporter->isFarmerSeller() && $review->farmer_seller_id !== $reporter->id) {
            throw ValidationException::withMessages([
                'target_id' => 'You can only report reviews on your own shop.',
            ]);
        }

        return $review;
    }

    private function guardAgainstDuplicateOpenReport(User $reporter, Model $target): void
    {
        $alreadyOpen = Report::query()
            ->open()
            ->where('reporter_id', $reporter->id)
            ->where('reportable_type', $target->getMorphClass())
            ->where('reportable_id', $target->getKey())
            ->exists();

        if ($alreadyOpen) {
            throw ValidationException::withMessages([
                'target_id' => 'You already have an open report for this.',
            ]);
        }
    }
}
