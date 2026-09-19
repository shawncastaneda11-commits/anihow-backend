<?php

namespace App\Actions\Listings;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\User;
use App\Support\ListingStorage;
use Illuminate\Http\UploadedFile;

class CreateListingAction
{
    /**
     * Listings auto-publish. The Super Admin holds takedown power, not
     * pre-approval power: pre-approving every listing would strangle a live
     * market and would show as lag in the demo.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $farmerSeller, array $attributes, ?UploadedFile $image = null): Listing
    {
        if ($image !== null) {
            $attributes['image_path'] = ListingStorage::store($image);
        }

        $attributes['farmer_seller_id'] = $farmerSeller->id;
        $attributes['farm_id'] = $farmerSeller->farm_id;
        $attributes['status'] = ListingStatus::Published;
        $attributes['quantity_held'] = 0;

        return Listing::create($attributes);
    }
}
