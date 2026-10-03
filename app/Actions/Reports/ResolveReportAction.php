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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
        return DB::transaction(function () use ($report, $admin, $resolutionNote, $applyModeration, $moderationReason): Report {
            $locked = Report::query()
                ->whereKey($report->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== ReportStatus::Open) {
                throw ValidationException::withMessages([
                    'status' => 'This report was already closed.',
                ]);
            }

            $locked->load(['reportable', 'reporter']);

            if ($applyModeration) {
                $this->applyModeration($locked, $admin, $moderationReason ?? $resolutionNote);
            }

            $locked->update([
                'status' => ReportStatus::Resolved,
                'resolved_by' => $admin->id,
                'resolution_note' => $resolutionNote,
                'resolved_at' => now(),
            ]);

            $reporter = $locked->reporter;
            if ($reporter !== null) {
                $this->notifier->reportResolved($reporter, $locked);
            }

            return $locked;
        });
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
