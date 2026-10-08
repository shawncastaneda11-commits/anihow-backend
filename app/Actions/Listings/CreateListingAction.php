<?php

namespace App\Actions\Listings;

use App\Enums\HarvestRecordKind;
use App\Enums\ListingStatus;
use App\Enums\ProductCategory;
use App\Models\CropType;
use App\Models\Farm;
use App\Models\Listing;
use App\Models\User;
use App\Support\HarvestInput;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateListingAction
{
    public function __construct(
        private SyncListingImage $images,
        private WriteHarvestRecord $records,
    ) {}

    /**
     * Listings auto-publish. The Super Admin holds takedown power, not
     * pre-approval power: pre-approving every listing would strangle a live
     * market and would show as lag in the demo.
     *
     * @param  array<string, mixed>  $attributes
     */
    /**
     * @param  array<string, mixed>|null  $harvest
     */
    public function handle(User $farmerSeller, array $attributes, ?UploadedFile $image = null, ?array $harvest = null): Listing
    {
        if ($farmerSeller->farm_id === null) {
            throw ValidationException::withMessages([
                'farm' => 'Assign this seller to a farm first.',
            ]);
        }

        $farm = Farm::query()->find($farmerSeller->farm_id);
        self::assertValueAddedAllowed(
            $farm,
            isset($attributes['crop_type_id']) ? (int) $attributes['crop_type_id'] : null,
        );

        if ($image !== null) {
            $attributes['image_path'] = $this->images->store($image);
        }

        $attributes['farmer_seller_id'] = $farmerSeller->id;
        $attributes['farm_id'] = $farmerSeller->farm_id;
        $attributes['status'] = ListingStatus::Published;
        $attributes['quantity_held'] = 0;
        $attributes['stock_tracked_since'] = now();

        return DB::transaction(function () use ($farmerSeller, $attributes, $harvest): Listing {
            $upcoming = isset($attributes['available_from'])
                && $attributes['available_from'] !== null
                && $attributes['available_from'] !== ''
                && Carbon::parse((string) $attributes['available_from'])->isFuture();

            if ($upcoming) {
                $attributes['needs_actual_harvest'] = true;

                return Listing::create($attributes);
            }

            $attributes['needs_actual_harvest'] = false;

            if ($harvest !== null) {
                if (bccomp($harvest['quantity_good'], '0', 2) !== 1) {
                    throw ValidationException::withMessages([
                        'quantity_harvested' => 'Nothing left to sell after rejects',
                    ]);
                }

                $attributes['quantity_available'] = $harvest['quantity_good'];
                $attributes['harvested_on'] = $harvest['harvested_on'];
            }

            $listing = Listing::query()->whereKey(Listing::create($attributes)->id)->lockForUpdate()->firstOrFail();
            $payload = $harvest ?? $this->compatHarvest($listing);
            $this->records->write($listing, HarvestRecordKind::Initial, $payload, $farmerSeller->id);

            return $listing->refresh();
        });
    }

    /**
     * @return array{
     *     harvested_on: string,
     *     quantity_harvested: string,
     *     quantity_rejected: string,
     *     quantity_good: string,
     *     rejection_reason: null,
     *     rejection_note: null,
     *     production_cost: null,
     *     cost_breakdown: null
     * }
     */
    private function compatHarvest(Listing $listing): array
    {
        $quantity = HarvestInput::scale($listing->quantity_available);

        return [
            'harvested_on' => $listing->harvested_on?->toDateString() ?? now()->toDateString(),
            'quantity_harvested' => $quantity,
            'quantity_rejected' => '0.00',
            'quantity_good' => $quantity,
            'rejection_reason' => null,
            'rejection_note' => null,
            'production_cost' => null,
            'cost_breakdown' => null,
        ];
    }

    public static function assertValueAddedAllowed(?Farm $farm, ?int $cropTypeId): void
    {
        if ($cropTypeId === null || $farm === null || $farm->allowsValueAdded()) {
            return;
        }

        $category = CropType::query()->whereKey($cropTypeId)->value('category');
        $categoryValue = $category instanceof ProductCategory ? $category->value : $category;

        if ($categoryValue !== ProductCategory::ValueAdded->value) {
            return;
        }

        throw ValidationException::withMessages([
            'crop_type_id' => Farm::VALUE_ADDED_OFF_MESSAGE,
        ]);
    }
}
