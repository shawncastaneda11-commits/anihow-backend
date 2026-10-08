<?php

namespace App\Actions\Listings;

use App\Enums\ListingStatus;
use App\Enums\ProductCategory;
use App\Models\CropType;
use App\Models\Farm;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class CreateListingAction
{
    public function __construct(private SyncListingImage $images) {}

    /**
     * Listings auto-publish. The Super Admin holds takedown power, not
     * pre-approval power: pre-approving every listing would strangle a live
     * market and would show as lag in the demo.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $farmerSeller, array $attributes, ?UploadedFile $image = null): Listing
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

        return Listing::create($attributes);
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
