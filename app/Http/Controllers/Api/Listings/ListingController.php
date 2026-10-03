<?php

namespace App\Http\Controllers\Api\Listings;

use App\Actions\Listings\CreateListingAction;
use App\Actions\Listings\DeleteListingAction;
use App\Actions\Listings\UpdateListingAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Listings\StoreListingRequest;
use App\Http\Requests\Api\Listings\UpdateListingRequest;
use App\Http\Resources\Api\ListingResource;
use App\Models\Listing;
use App\Support\ShopReviews;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListingController extends Controller
{
    /**
     * @return array<int|string, mixed>
     */
    public static function relations(): array
    {
        return [
            'cropType',
            'farm',
            'activeTawadRule',
            'farmerSeller' => fn ($query) => $query
                ->withAvg(ShopReviews::receivedAggregate(), 'rating')
                ->withCount(ShopReviews::receivedAggregate()),
        ];
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Listing::class);

        $listings = $request->user()
            ->listings()
            ->with(self::relations())
            ->latest()
            ->paginate();

        return ListingResource::collection($listings);
    }

    public function store(StoreListingRequest $request, CreateListingAction $createListing): JsonResponse
    {
        $listing = $createListing->handle(
            $request->user(),
            $request->listingAttributes(),
            $request->file('image'),
        );

        return (new ListingResource($listing->load(self::relations())))
            ->additional(['message' => 'Listing created.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Listing $listing): ListingResource
    {
        $this->authorize('view', $listing);

        $listing->load(self::relations());

        return new ListingResource($listing);
    }

    public function update(
        UpdateListingRequest $request,
        Listing $listing,
        UpdateListingAction $updateListing,
    ): ListingResource {
        $listing = $updateListing->handle(
            $listing,
            $request->listingAttributes(),
            $request->file('image'),
        );

        return (new ListingResource($listing->load(self::relations())))
            ->additional(['message' => 'Listing updated.']);
    }

    public function destroy(Listing $listing, DeleteListingAction $deleteListing): JsonResponse
    {
        $this->authorize('delete', $listing);

        $deleteListing->handle($listing);

        return response()->json(['message' => 'Listing deleted.']);
    }
}
