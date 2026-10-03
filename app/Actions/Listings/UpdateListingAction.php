<?php

namespace App\Actions\Listings;

use App\Models\Listing;
use Illuminate\Http\UploadedFile;

class UpdateListingAction
{
    public function __construct(private SyncListingImage $images) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Listing $listing, array $attributes, ?UploadedFile $image = null): Listing
    {
        if ($image !== null) {
            $attributes['image_path'] = $this->images->replace($listing->image_path, $image);
        }

        // farm_id is denormalized from the seller and is never set from input.
        unset($attributes['farm_id'], $attributes['farmer_seller_id'], $attributes['quantity_held'], $attributes['status']);

        $listing->update($attributes);

        return $listing->refresh();
    }
}
