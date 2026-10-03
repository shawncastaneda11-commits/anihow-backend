<?php

namespace App\Http\Controllers\Api\Favorites;

use App\Actions\Favorites\AddShopFavoriteAction;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Favorites\StoreShopFavoriteRequest;
use App\Http\Resources\Api\ShopFavoriteResource;
use App\Models\ShopFavorite;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ShopFavoriteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ShopFavorite::class);

        $favorites = $request->user()
            ->shopFavorites()
            ->whereHas('farmerSeller', function (Builder $query): void {
                $query->withoutTrashed()->where('status', UserStatus::Active);
            })
            ->with($this->shopRelations())
            ->latest()
            ->paginate();

        return ShopFavoriteResource::collection($favorites);
    }

    public function store(StoreShopFavoriteRequest $request, AddShopFavoriteAction $addFavorite): JsonResponse
    {
        $favorite = $addFavorite->handle($request->user(), $request->farmerSeller());

        return (new ShopFavoriteResource($favorite->load($this->shopRelations())))
            ->additional(['message' => 'Shop added to favorites.'])
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Request $request, User $farmerSeller): JsonResponse
    {
        $favorite = $request->user()
            ->shopFavorites()
            ->where('farmer_seller_id', $farmerSeller->id)
            ->firstOrFail();

        $this->authorize('delete', $favorite);
        $favorite->delete();

        return response()->json([
            'message' => 'Shop removed from favorites.',
        ]);
    }

    /**
     * @return array<int|string, mixed>
     */
    private function shopRelations(): array
    {
        $visible = ['reviewsReceived' => fn (Builder $query): Builder => $query->where('is_removed', false)];

        return [
            'farmerSeller' => fn ($query) => $query
                ->with('farm')
                ->withAvg($visible, 'rating')
                ->withCount($visible),
        ];
    }
}
