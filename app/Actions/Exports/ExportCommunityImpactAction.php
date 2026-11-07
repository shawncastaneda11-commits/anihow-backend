<?php

namespace App\Actions\Exports;

use App\Enums\ListingStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\ActivityLog;
use App\Models\Farm;
use App\Models\Listing;
use App\Models\Order;
use App\Models\User;
use App\Support\CsvDownload;
use Carbon\CarbonInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * COSEL's community impact report: one row per partner farm for a period,
 * the numbers an extension office reports upward. Spreadsheet (CSV, opens in
 * Excel). The download is recorded in the activity log.
 *
 * Active farmer-sellers and published listings are as of today; the order
 * figures cover the chosen period by completion date.
 */
class ExportCommunityImpactAction
{
    public function __construct(private readonly CsvDownload $csv) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function rows(CarbonInterface $since, CarbonInterface $until): array
    {
        return Farm::query()
            ->orderBy('name')
            ->get()
            ->map(function (Farm $farm) use ($since, $until): array {
                $completed = Order::query()
                    ->where('farm_id', $farm->getKey())
                    ->where('status', OrderStatus::Completed)
                    ->whereBetween('completed_at', [$since, $until]);

                return [
                    'Farm' => $farm->name,
                    'Municipality' => $farm->municipality,
                    'Partner since' => $farm->partnered_since?->toDateString(),
                    'Active farmer-sellers' => User::query()
                        ->where('farm_id', $farm->getKey())
                        ->where('status', UserStatus::Active)
                        ->role(Role::FarmerSeller->value)
                        ->count(),
                    'Published listings' => Listing::query()
                        ->where('farm_id', $farm->getKey())
                        ->where('status', ListingStatus::Published)
                        ->count(),
                    'Completed orders (app)' => (clone $completed)->where('source', '!=', OrderSource::WalkIn)->count(),
                    'Walk-in sales' => (clone $completed)->where('source', OrderSource::WalkIn)->count(),
                    'Sales (PHP)' => round((float) (clone $completed)->sum('total'), 2),
                    'Buyers served (app)' => (clone $completed)->whereNotNull('buyer_id')
                        ->where('source', '!=', OrderSource::WalkIn)
                        ->distinct()
                        ->count('buyer_id'),
                ];
            })
            ->all();
    }

    public function download(User $user, CarbonInterface $since, CarbonInterface $until): StreamedResponse
    {
        $rows = $this->rows($since, $until);

        ActivityLog::query()->create([
            'user_id' => $user->getKey(),
            'action' => 'exported',
            'subject_type' => 'community impact report',
            'subject_label' => $since->toDateString().' to '.$until->toDateString(),
            'changes' => ['farms' => count($rows)],
        ]);

        $name = 'anihow-community-impact-'.$since->format('Ymd').'-'.$until->format('Ymd').'.csv';

        return $this->csv->stream($name, function ($handle) use ($rows, $since, $until): void {
            fputcsv($handle, ['AniHow community impact report', $since->toDateString().' to '.$until->toDateString()]);
            fputcsv($handle, []);
            fputcsv($handle, array_keys($rows[0] ?? ['Farm' => null]));

            foreach ($rows as $row) {
                fputcsv($handle, $this->csv->row(array_values($row)));
            }
        });
    }
}
