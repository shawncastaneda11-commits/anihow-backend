<?php

namespace App\Http\Controllers\Api\Favorites;

use App\Actions\Favorites\AddFarmFavoriteAction;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Favorites\StoreFarmFavoriteRequest;
use App\Http\Resources\Api\FarmFavoriteResource;
use App\Models\Farm;
use App\Models\FarmFavorite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FarmFavoriteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', FarmFavorite::class);

        $favorites = $request->user()
            ->farmFavorites()
            ->whereHas('farm', fn ($query) => $query->active())
            ->with(['farm' => fn ($query) => $query->withCount([
                'farmerSellers' => fn ($sellers) => $sellers
                    ->role(Role::FarmerSeller->value)
                    ->where('status', UserStatus::Active),
            ])])
            ->latest()
            ->get();

        return FarmFavoriteResource::collection($favorites);
    }

    public function store(StoreFarmFavoriteRequest $request, AddFarmFavoriteAction $addFavorite): JsonResponse
    {
        $favorite = $addFavorite->handle($request->user(), $request->farm());
        $favorite->load(['farm' => fn ($query) => $query->withCount('farmerSellers')]);

        return (new FarmFavoriteResource($favorite))
            ->additional(['message' => 'Farm added to favorites.'])
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Request $request, Farm $farm): JsonResponse
    {
        $favorite = $request->user()
            ->farmFavorites()
            ->where('farm_id', $farm->id)
            ->firstOrFail();

        $this->authorize('delete', $favorite);
        $favorite->delete();

        return response()->json([
            'message' => 'Farm removed from favorites.',
        ]);
    }
}
