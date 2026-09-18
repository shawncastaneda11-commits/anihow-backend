<?php

namespace App\Http\Controllers\Api\Listings;

use App\Actions\Listings\ToggleListingActiveAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Listings\ToggleListingActiveRequest;
use App\Http\Resources\Api\ListingResource;
use App\Models\Listing;

class ToggleListingActiveController extends Controller
{
    public function __invoke(
        ToggleListingActiveRequest $request,
        Listing $listing,
        ToggleListingActiveAction $toggleListingActive,
    ): ListingResource {
        $listing = $toggleListingActive->handle(
            $listing,
            $request->has('is_active') ? $request->boolean('is_active') : null,
        );

        return (new ListingResource($listing))
            ->additional(['message' => 'Listing visibility updated.']);
    }
}
