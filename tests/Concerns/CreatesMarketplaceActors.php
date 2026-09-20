<?php

namespace Tests\Concerns;

use App\Enums\FulfillmentPreference;
use App\Enums\Role;
use App\Models\CropType;
use App\Models\Farm;
use App\Models\Listing;
use App\Models\Order;
use App\Models\User;
use Illuminate\Testing\TestResponse;

trait CreatesMarketplaceActors
{
    protected function farm(): Farm
    {
        return Farm::factory()->create();
    }

    protected function cropType(array $attributes = []): CropType
    {
        return CropType::factory()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function farmer(array $attributes = [], ?Farm $farm = null): User
    {
        $farm ??= Farm::factory()->create();
        $farmer = User::factory()->create(['farm_id' => $farm->id] + $attributes);
        $farmer->syncRoles(Role::FarmerSeller);

        return $farmer;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function buyer(array $attributes = []): User
    {
        $buyer = User::factory()->create($attributes);
        $buyer->syncRoles(Role::Buyer);

        return $buyer;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function listingFor(User $farmer, array $attributes = []): Listing
    {
        return Listing::factory()->forFarmer($farmer)->create($attributes);
    }

    protected function asUser(User $user): static
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('mobile')->plainTextToken);
    }

    protected function addToCart(User $buyer, Listing $listing, float $quantity): void
    {
        $this->asUser($buyer)
            ->postJson('/api/buyer/cart', [
                'listing_id' => $listing->id,
                'quantity' => $quantity,
            ])
            ->assertCreated();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function checkout(User $buyer, array $payload = []): TestResponse
    {
        return $this->asUser($buyer)->postJson('/api/buyer/checkout', [
            'fulfillment_preference' => FulfillmentPreference::BuyerPickup->value,
            ...$payload,
        ]);
    }

    protected function placeOrder(User $buyer, Listing $listing, float $quantity = 1): Order
    {
        $this->addToCart($buyer, $listing, $quantity);

        $response = $this->checkout($buyer)->assertCreated();

        return Order::query()->findOrFail($response->json('data.0.id'));
    }

    protected function completeOrder(User $farmer, Order $order, float $amountReceived): Order
    {
        $this->asUser($farmer)
            ->patchJson("/api/farmer/orders/{$order->id}/confirm")
            ->assertOk();
        $this->asUser($farmer)
            ->patchJson("/api/farmer/orders/{$order->id}/ready")
            ->assertOk();
        $this->asUser($farmer)
            ->patchJson("/api/farmer/orders/{$order->id}/complete", [
                'amount_received' => $amountReceived,
            ])
            ->assertOk();

        return $order->refresh();
    }
}
