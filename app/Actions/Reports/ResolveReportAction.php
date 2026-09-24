<?php

namespace App\Actions\Reports;

use App\Actions\Listings\TakeDownListingAction;
use App\Actions\Reviews\RemoveReviewAction;
use App\Enums\ListingStatus;
use App\Enums\ReportStatus;
use App\Models\Listing;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use App\Support\InAppNotifier;

class ResolveReportAction
{
    public function __construct(
        private TakeDownListingAction $takeDownListing,
        private RemoveReviewAction $removeReview,
        private InAppNotifier $notifier,
    ) {}

    public function handle(
        Report $report,
        User $admin,
        string $resolutionNote,
        bool $applyModeration = false,
        ?string $moderationReason = null,
    ): Report {
        if ($applyModeration) {
            $this->applyModeration($report, $admin, $moderationReason ?? $resolutionNote);
        }

        $report->update([
            'status' => ReportStatus::Resolved,
            'resolved_by' => $admin->id,
            'resolution_note' => $resolutionNote,
            'resolved_at' => now(),
        ]);

        $reporter = $report->reporter;
        if ($reporter !== null) {
            $this->notifier->reportResolved($reporter, $report);
        }

        return $report;
    }

    private function applyModeration(Report $report, User $admin, string $reason): void
    {
        $target = $report->reportable;

        if ($target instanceof Listing && $target->status === ListingStatus::Published) {
            $this->takeDownListing->handle($target, $admin, $reason);

            return;
        }

        if ($target instanceof Review && ! $target->is_removed) {
            $this->removeReview->handle($target, $admin, $reason);
        }
    }
}
