<?php

namespace App\Actions\Listings;

use App\Enums\ListingStatus;
use App\Models\CartItem;
use App\Models\Listing;
use App\Models\User;
use App\Support\InAppNotifier;

class TakeDownListingAction
{
    public function __construct(private InAppNotifier $notifier) {}

    public function handle(Listing $listing, User $moderator, string $reason): Listing
    {
        $listing->forceFill([
            'status' => ListingStatus::TakenDown,
            'is_active' => false,
            'taken_down_at' => now(),
            'taken_down_by' => $moderator->id,
            'takedown_reason' => $reason,
        ])->save();

        CartItem::query()->where('listing_id', $listing->id)->delete();

        $listing->loadMissing('farmerSeller');

        $this->notifier->listingTakenDown($listing->farmerSeller, $listing);

        return $listing;
    }
}
