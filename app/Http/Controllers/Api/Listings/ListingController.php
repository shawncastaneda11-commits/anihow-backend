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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListingController extends Controller
{
    private const RELATIONS = ['cropType', 'farmerSeller', 'farm', 'activeTawadRule'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Listing::class);

        $listings = $request->user()
            ->listings()
            ->with(self::RELATIONS)
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

        return (new ListingResource($listing->load(self::RELATIONS)))
            ->additional(['message' => 'Listing created.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Listing $listing): ListingResource
    {
        $this->authorize('view', $listing);

        $listing->load(self::RELATIONS);

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

        return (new ListingResource($listing->load(self::RELATIONS)))
            ->additional(['message' => 'Listing updated.']);
    }

    public function destroy(Listing $listing, DeleteListingAction $deleteListing): JsonResponse
    {
        $this->authorize('delete', $listing);

        $deleteListing->handle($listing);

        return response()->json(['message' => 'Listing deleted.']);
    }
}
