<?php

namespace App\Actions\Listings;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Http\UploadedFile;

class CreateListingAction
{
    public function __construct(private SyncListingImage $images) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $farmer, array $data, ?UploadedFile $image = null): Listing
    {
        $listing = $farmer->listings()->create([
            ...$data,
            'is_active' => $data['is_active'] ?? true,
            'image_path' => $image ? $this->images->store($image) : null,
        ]);

        return $listing->load(['category', 'farmerSeller']);
    }
}
