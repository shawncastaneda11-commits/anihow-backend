<?php

namespace App\Http\Controllers\Api\Listings;

use App\Actions\Listings\RemoveStockAction;
use App\Enums\StockRemovalReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Listings\StoreStockRemovalRequest;
use App\Http\Resources\Api\ListingResource;
use App\Models\Listing;

class StockRemovalController extends Controller
{
    public function store(StoreStockRemovalRequest $request, Listing $listing, RemoveStockAction $action): ListingResource
    {
        $listing = $action->handle(
            $listing,
            (string) $request->validated('quantity'),
            StockRemovalReason::from($request->validated('reason')),
            $request->validated('note'),
            $request->user(),
        );

        $fresh = Listing::withTrashed()
            ->with(ListingController::relations())
            ->withActiveReservationTotals()
            ->withExists('harvestRecords')
            ->findOrFail($listing->id);

        return (new ListingResource($fresh))->additional(['message' => 'Stock removed.']);
    }
}
