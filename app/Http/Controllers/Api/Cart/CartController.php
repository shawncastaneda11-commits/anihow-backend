<?php

namespace App\Http\Controllers\Api\Cart;

use App\Actions\Reservations\OpenDueReservations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Cart\StoreCartItemRequest;
use App\Http\Requests\Api\Cart\UpdateCartItemRequest;
use App\Http\Resources\Api\CartItemResource;
use App\Models\CartItem;
use App\Models\Listing;
use App\Support\ShopReviews;
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

        CartItem::pruneUnavailable($request->user());

        $items = $request->user()
            ->cartItems()
            ->with($this->cartRelations())
            ->latest()
            ->get();

        return CartItemResource::collection($items);
    }

    public function store(StoreCartItemRequest $request): JsonResponse
    {
        app(OpenDueReservations::class)->forListing((int) $request->validated('listing_id'));

        $listing = Listing::query()
            ->with('cropType')
            ->findOrFail($request->validated('listing_id'));

        if (! Listing::query()->listedForBuyers()->whereKey($listing->id)->exists()) {
            abort(404);
        }

        if (! Listing::query()->allowedByFarmFeatures()->whereKey($listing->id)->exists()) {
            throw ValidationException::withMessages([
                'listing_id' => "{$listing->title} is no longer available.",
            ]);
        }

        if ($listing->isUpcoming() || $listing->isExpired()) {
            throw ValidationException::withMessages([
                'listing_id' => $listing->isExpired()
                    ? "{$listing->title} is no longer available."
                    : "{$listing->title} is not available yet.",
            ]);
        }

        $existing = $request->user()
            ->cartItems()
            ->where('listing_id', $listing->id)
            ->first();

        $total = (float) $request->validated('quantity') + (float) ($existing->quantity ?? 0);

        $this->assertOrderQuantity($listing, $total);
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
        app(OpenDueReservations::class)->forListing((int) $cartItem->listing_id);
        $cartItem->unsetRelation('listing');

        $listing = $cartItem->listing;
        if ($listing !== null && $listing->isUpcoming()) {
            throw ValidationException::withMessages([
                'quantity' => "{$listing->title} is not available yet.",
            ]);
        }

        if ($listing === null || ! Listing::query()->buyerVisible()->whereKey($listing->id)->exists()) {
            $cartItem->delete();

            throw ValidationException::withMessages([
                'quantity' => 'This listing is no longer available.',
            ]);
        }

        if (! Listing::query()->allowedByFarmFeatures()->whereKey($listing->id)->exists()) {
            throw ValidationException::withMessages([
                'quantity' => "{$listing->title} is no longer available.",
            ]);
        }

        $quantity = (float) $request->validated('quantity');

        $this->assertOrderQuantity($listing, $quantity);
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
    private function assertOrderQuantity(Listing $listing, float $quantity): void
    {
        $left = $listing->sellableQuantity();

        if (Listing::orderHundredths($left) < Listing::orderHundredths((float) $listing->min_order_quantity)) {
            throw ValidationException::withMessages([
                'quantity' => $listing->belowMinimumStockMessage($left),
            ]);
        }

        if (! $listing->allowsOrderQuantity($quantity)) {
            throw ValidationException::withMessages([
                'quantity' => $listing->orderQuantityMessage(),
            ]);
        }
    }

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
}
