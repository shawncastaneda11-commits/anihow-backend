<?php

namespace App\Actions\Reports;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use App\Support\InAppNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DismissReportAction
{
    public function __construct(private InAppNotifier $notifier) {}

    public function handle(Report $report, User $admin): Report
    {
        return DB::transaction(function () use ($report, $admin): Report {
            $locked = Report::query()
                ->whereKey($report->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== ReportStatus::Open) {
                throw ValidationException::withMessages([
                    'status' => 'This report was already closed.',
                ]);
            }

            $locked->load('reporter');

            $locked->update([
                'status' => ReportStatus::Dismissed,
                'resolved_by' => $admin->id,
                'resolved_at' => now(),
            ]);

            $reporter = $locked->reporter;
            if ($reporter !== null) {
                $this->notifier->reportDismissed($reporter, $locked);
            }

            return $locked;
        });
    }
}
