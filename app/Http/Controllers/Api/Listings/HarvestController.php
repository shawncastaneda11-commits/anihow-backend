<?php

namespace App\Http\Controllers\Api\Listings;

use App\Actions\Listings\AddStockAction;
use App\Actions\Listings\RecordActualHarvestAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Listings\RecordActualHarvestRequest;
use App\Http\Requests\Api\Listings\StoreHarvestRequest;
use App\Http\Resources\Api\ListingResource;
use App\Models\Listing;

class HarvestController extends Controller
{
    public function store(StoreHarvestRequest $request, Listing $listing, AddStockAction $action): ListingResource
    {
        $listing = $action->handle($listing, $request->harvest(), $request->user());

        return $this->resource($listing)->additional(['message' => 'Stock added.']);
    }

    public function actual(
        RecordActualHarvestRequest $request,
        Listing $listing,
        RecordActualHarvestAction $action,
    ): ListingResource {
        $listing = $action->handle(
            $listing,
            $request->harvest(),
            $request->user(),
            $request->boolean('confirm_cancel_reservations'),
        );

        return $this->resource($listing)->additional(['message' => 'Actual harvest recorded.']);
    }

    private function resource(Listing $listing): ListingResource
    {
        $fresh = Listing::withTrashed()
            ->with(ListingController::relations())
            ->withActiveReservationTotals()
            ->withExists('harvestRecords')
            ->findOrFail($listing->id);

        return new ListingResource($fresh);
    }
}
