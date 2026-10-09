<?php

namespace App\Console\Commands;

use App\Actions\Listings\EnsureHarvestRecorded;
use App\Models\Listing;
use App\Support\InAppNotifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('listings:harvest-upkeep')]
#[Description('Record estimated harvests and remind sellers about stock that still needs a harvest')]
class HarvestUpkeepCommand extends Command
{
    public function handle(EnsureHarvestRecorded $ensure, InAppNotifier $notifier): int
    {
        Listing::query()
            ->where('needs_actual_harvest', true)
            ->where(function ($query): void {
                $query->whereNull('available_from')->orWhere('available_from', '<=', now());
            })
            ->orderBy('id')
            ->each(function (Listing $listing) use ($ensure): void {
                $ensure->forListing($listing);
            });

        Listing::query()
            ->with('farmerSeller')
            ->where('needs_actual_harvest', true)
            ->whereNull('harvest_reminded_at')
            ->whereNotNull('available_from')
            ->where('available_from', '>', now())
            ->where('available_from', '<=', now()->addDay())
            ->orderBy('id')
            ->each(function (Listing $listing) use ($notifier): void {
                $claimed = Listing::query()
                    ->whereKey($listing->id)
                    ->whereNull('harvest_reminded_at')
                    ->update(['harvest_reminded_at' => now()]);

                if ($claimed !== 1) {
                    return;
                }

                $farmer = $listing->farmerSeller;

                if ($farmer !== null) {
                    $notifier->harvestReminder($farmer, $listing);
                }
            });

        Listing::query()
            ->with('farmerSeller')
            ->expired()
            ->where('quantity_available', '>', 0)
            ->whereNull('expired_stock_notified_at')
            ->orderBy('id')
            ->each(function (Listing $listing) use ($notifier): void {
                $claimed = Listing::query()
                    ->whereKey($listing->id)
                    ->whereNull('expired_stock_notified_at')
                    ->update(['expired_stock_notified_at' => now()]);

                if ($claimed !== 1) {
                    return;
                }

                $farmer = $listing->farmerSeller;

                if ($farmer !== null) {
                    $notifier->expiredStockLeft($farmer, $listing->refresh());
                }
            });

        return self::SUCCESS;
    }
}
