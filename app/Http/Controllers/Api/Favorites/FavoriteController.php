<?php

namespace App\Http\Controllers\Api\Favorites;

use App\Actions\Favorites\AddFavoriteAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Favorites\StoreFavoriteRequest;
use App\Http\Resources\Api\FavoriteResource;
use App\Models\Favorite;
use App\Models\Listing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FavoriteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Favorite::class);

        $favorites = $request->user()
            ->favorites()
            ->with(['listing.category', 'listing.farmerSeller'])
            ->latest()
            ->paginate();

        return FavoriteResource::collection($favorites);
    }

    public function store(StoreFavoriteRequest $request, AddFavoriteAction $addFavorite): JsonResponse
    {
        $favorite = $addFavorite->handle($request->user(), $request->listing());

        return (new FavoriteResource($favorite->load(['listing.category', 'listing.farmerSeller'])))
            ->additional(['message' => 'Listing added to favorites.'])
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Request $request, Listing $listing): JsonResponse
    {
        $favorite = $request->user()
            ->favorites()
            ->where('listing_id', $listing->id)
            ->firstOrFail();

        $this->authorize('delete', $favorite);
        $favorite->delete();

        return response()->json([
            'message' => 'Listing removed from favorites.',
        ]);
    }
}
