<?php

namespace App\Actions\Reports;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use App\Support\InAppNotifier;

class DismissReportAction
{
    public function __construct(private InAppNotifier $notifier) {}

    public function handle(Report $report, User $admin): Report
    {
        $report->update([
            'status' => ReportStatus::Dismissed,
            'resolved_by' => $admin->id,
            'resolved_at' => now(),
        ]);

        $reporter = $report->reporter;
        if ($reporter !== null) {
            $this->notifier->reportDismissed($reporter, $report);
        }

        return $report;
    }
}
