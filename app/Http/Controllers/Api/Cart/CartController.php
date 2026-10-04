<?php

namespace App\Http\Controllers\Api\Cart;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Cart\StoreCartItemRequest;
use App\Http\Requests\Api\Cart\UpdateCartItemRequest;
use App\Http\Resources\Api\CartItemResource;
use App\Models\CartItem;
use App\Models\Listing;
use App\Models\User;
use App\Support\ShopReviews;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    /**
     * @return array<int|string, mixed>
     */
    private function cartRelations(): array
    {
        return [
            'listing.cropType',
            'listing.farm',
            'listing.activeTawadRule',
            'listing.farmerSeller' => fn ($query) => $query
                ->withAvg(ShopReviews::receivedAggregate(), 'rating')
                ->withCount(ShopReviews::receivedAggregate()),
        ];
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CartItem::class);

        $this->pruneUnavailableCartItems($request->user());

        $items = $request->user()
            ->cartItems()
            ->with($this->cartRelations())
            ->latest()
            ->get();

        return CartItemResource::collection($items);
    }

    public function store(StoreCartItemRequest $request): JsonResponse
    {
        $listing = Listing::query()
            ->buyerVisible()
            ->with('cropType')
            ->findOrFail($request->validated('listing_id'));

        $existing = $request->user()
            ->cartItems()
            ->where('listing_id', $listing->id)
            ->first();

        $total = (float) $request->validated('quantity') + (float) ($existing->quantity ?? 0);

        $this->assertStock($listing, $total);

        $item = $request->user()->cartItems()->updateOrCreate(
            ['listing_id' => $listing->id],
            ['quantity' => $total],
        );

        $item->load($this->cartRelations());

        return (new CartItemResource($item))
            ->additional(['message' => 'Added to cart.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateCartItemRequest $request, CartItem $cartItem): CartItemResource
    {
        $listing = $cartItem->listing;
        if ($listing === null || ! Listing::query()->buyerVisible()->whereKey($listing->id)->exists()) {
            $cartItem->delete();

            throw ValidationException::withMessages([
                'quantity' => 'This listing is no longer available.',
            ]);
        }

        $quantity = (float) $request->validated('quantity');

        $this->assertStock($listing, $quantity);

        $cartItem->update(['quantity' => $quantity]);
        $cartItem->load($this->cartRelations());

        return (new CartItemResource($cartItem))
            ->additional(['message' => 'Cart updated.']);
    }

    public function destroy(CartItem $cartItem): JsonResponse
    {
        $this->authorize('delete', $cartItem);

        $cartItem->delete();

        return response()->json(['message' => 'Removed from cart.']);
    }

    /**
     * Sellable quantity, not quantity_available: stock held by other buyers'
     * placed orders is not available to this cart.
     */
    private function assertStock(Listing $listing, float $quantity): void
    {
        if ($listing->hasStockFor($quantity)) {
            return;
        }

        $available = number_format($listing->sellableQuantity(), 2, '.', '');

        throw ValidationException::withMessages([
            'quantity' => "Only {$available} available.",
        ]);
    }

    private function pruneUnavailableCartItems(User $buyer): void
    {
        $buyer->cartItems()
            ->whereDoesntHave(
                'listing',
                fn (Builder $listing): Builder => $listing->marketplaceVisible(),
            )
            ->delete();
    }
}
