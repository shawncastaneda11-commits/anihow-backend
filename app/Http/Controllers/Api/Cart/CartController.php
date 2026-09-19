<?php

namespace App\Http\Controllers\Api\Cart;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Cart\StoreCartItemRequest;
use App\Http\Requests\Api\Cart\UpdateCartItemRequest;
use App\Http\Resources\Api\CartItemResource;
use App\Models\CartItem;
use App\Models\Listing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    private const CART_RELATIONS = [
        'listing.cropType',
        'listing.farmerSeller',
        'listing.activeTawadRule',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CartItem::class);

        $items = $request->user()
            ->cartItems()
            ->with(self::CART_RELATIONS)
            ->latest()
            ->get();

        return CartItemResource::collection($items);
    }

    public function store(StoreCartItemRequest $request): JsonResponse
    {
        $listing = Listing::query()
            ->marketplaceVisible()
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

        $item->load(self::CART_RELATIONS);

        return (new CartItemResource($item))
            ->additional(['message' => 'Added to cart.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateCartItemRequest $request, CartItem $cartItem): CartItemResource
    {
        $quantity = (float) $request->validated('quantity');

        $this->assertStock($cartItem->listing, $quantity);

        $cartItem->update(['quantity' => $quantity]);
        $cartItem->load(self::CART_RELATIONS);

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
}
