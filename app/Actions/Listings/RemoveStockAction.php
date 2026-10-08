<?php

namespace App\Actions\Listings;

use App\Enums\ProductCategory;
use App\Enums\StockRemovalReason;
use App\Models\Listing;
use App\Models\StockRemoval;
use App\Models\User;
use App\Support\HarvestInput;
use App\Support\InAppNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemoveStockAction
{
    public function __construct(private InAppNotifier $notifier) {}

    public function handle(
        Listing $listing,
        string $quantity,
        StockRemovalReason $reason,
        ?string $note,
        User $actor,
    ): Listing {
        return DB::transaction(function () use ($listing, $quantity, $reason, $note, $actor): Listing {
            $locked = Listing::withTrashed()->whereKey($listing->id)->lockForUpdate()->firstOrFail();
            $locked->loadMissing('cropType');

            if ($locked->trashed()) {
                throw ValidationException::withMessages([
                    'listing' => 'This listing has been deleted.',
                ]);
            }

            if ($locked->needs_actual_harvest) {
                throw ValidationException::withMessages([
                    'listing' => 'Record the actual harvest first',
                ]);
            }

            $amount = HarvestInput::scale($quantity);
            $sellable = HarvestInput::scale($locked->sellableQuantity());
            $held = HarvestInput::scale($locked->quantity_held);

            if (bccomp($amount, '0', 2) !== 1 || bccomp($amount, $sellable, 2) === 1) {
                $unit = $locked->unit?->value ?? '';

                throw ValidationException::withMessages([
                    'quantity' => "Only {$sellable} {$unit} can be removed ({$held} {$unit} held by orders).",
                ]);
            }

            $before = HarvestInput::scale($locked->quantity_available);
            $after = bcsub($before, $amount, 2);
            $locked->quantity_available = $after;
            $locked->save();

            StockRemoval::query()->create([
                'listing_id' => $locked->id,
                'farmer_seller_id' => $locked->farmer_seller_id,
                'farm_id' => $locked->farm_id,
                'crop_type_id' => $locked->crop_type_id,
                'unit' => $locked->unit?->value ?? (string) $locked->getRawOriginal('unit'),
                'is_value_added' => $locked->cropType?->category === ProductCategory::ValueAdded,
                'quantity' => $amount,
                'reason' => $reason,
                'note' => $note,
                'price_per_unit' => $locked->price_per_unit,
                'recorded_by' => $actor->id,
            ]);

            $threshold = number_format((float) config('anihow.low_stock_threshold', 5), 2, '.', '');

            if (bccomp($before, $threshold, 2) >= 0 && bccomp($after, $threshold, 2) < 0) {
                $farmer = $locked->farmerSeller()->first();

                if ($farmer) {
                    $this->notifier->listingLowStock($farmer, $locked->refresh());
                }
            }

            return $locked->refresh();
        });
    }
}
