<?php

namespace App\Actions\Listings;

use App\Models\Listing;
use App\Support\ListingStorage;
use Illuminate\Http\UploadedFile;

class UpdateListingAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Listing $listing, array $attributes, ?UploadedFile $image = null): Listing
    {
        if ($image !== null) {
            $previous = $listing->image_path;
            $attributes['image_path'] = ListingStorage::store($image);

            if (filled($previous)) {
                ListingStorage::disk()->delete($previous);
            }
        }

        // farm_id is denormalized from the seller and is never set from input.
        unset($attributes['farm_id'], $attributes['farmer_seller_id'], $attributes['quantity_held'], $attributes['status']);

        $listing->update($attributes);

        return $listing->refresh();
    }
}
