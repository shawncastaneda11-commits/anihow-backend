<?php

namespace App\Actions\Listings;

use App\Models\Listing;
use Illuminate\Http\UploadedFile;

class UpdateListingAction
{
    public function __construct(private SyncListingImage $images) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Listing $listing, array $data, ?UploadedFile $image = null): Listing
    {
        if ($image) {
            $data['image_path'] = $this->images->replace($listing->image_path, $image);
        }

        $listing->update($data);

        return $listing->refresh()->load(['category', 'farmerSeller']);
    }
}
