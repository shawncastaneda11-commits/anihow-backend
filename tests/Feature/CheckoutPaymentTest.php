<?php

namespace Tests\Feature;

use App\Actions\Chat\SendStallMessage;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\StallMessage;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class CheckoutPaymentTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_checkout_defaults_to_cash_and_sends_no_chat_message(): void
    {
        $farmer = $this->farmer(['shop_name' => 'Nena Stall']);
        $listing = $this->listingFor($farmer, ['price_per_unit' => 30, 'quantity_available' => 10]);
        $buyer = $this->buyer();
        $this->addToCart($buyer, $listing, 1);

        $orderId = $this->checkout($buyer)->assertCreated()->json('data.0.id');

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'payment_method' => PaymentMethod::CashOnHandover->value,
        ]);
        $this->assertSame(0, StallMessage::query()->count());
    }

    public function test_online_payment_is_stored_and_posts_one_buyer_message(): void
    {
        $farmer = $this->farmer(['shop_name' => 'Nena Stall']);
        $listing = $this->listingFor($farmer, ['price_per_unit' => 30, 'quantity_available' => 10]);
        $buyer = $this->buyer(['name' => 'Carla Santos']);
        $this->addToCart($buyer, $listing, 1);

        $response = $this->checkout($buyer, [
            'payments' => [
                ['seller_id' => $farmer->id, 'method' => PaymentMethod::OnlineTransfer->value],
            ],
        ])->assertCreated();

        $order = Order::query()->findOrFail($response->json('data.0.id'));

        $this->assertSame(PaymentMethod::OnlineTransfer->value, $order->payment_method);
        $this->assertSame(PaymentMethod::OnlineTransfer->label(), $response->json('data.0.payment_label'));
        $this->assertSame(1, StallMessage::query()->count());

        $message = StallMessage::query()->firstOrFail();
        $this->assertSame($buyer->id, $message->user_id);
        $this->assertSame($order->id, $message->order_id);
        $this->assertStringContainsString($order->order_number, (string) $message->body);
        $this->assertStringContainsString('₱30.00', (string) $message->body);
        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $farmer->id,
            'type' => 'order_message',
        ]);
    }

    public function test_a_split_cart_messages_only_the_online_seller(): void
    {
        $farm = $this->farm();
        $cashSeller = $this->farmer(['email' => 'cash@example.com', 'shop_name' => 'Cash Stall'], $farm);
        $onlineSeller = $this->farmer(['email' => 'online@example.com', 'shop_name' => 'QR Stall'], $farm);
        $cashListing = $this->listingFor($cashSeller, ['price_per_unit' => 30, 'quantity_available' => 10]);
        $onlineListing = $this->listingFor($onlineSeller, ['price_per_unit' => 40, 'quantity_available' => 10]);
        $buyer = $this->buyer();
        $this->addToCart($buyer, $cashListing, 1);
        $this->addToCart($buyer, $onlineListing, 1);

        $this->checkout($buyer, [
            'payments' => [
                ['seller_id' => $cashSeller->id, 'method' => PaymentMethod::CashOnHandover->value],
                ['seller_id' => $onlineSeller->id, 'method' => PaymentMethod::OnlineTransfer->value],
            ],
        ])->assertCreated();

        $this->assertSame(1, StallMessage::query()->count());
        $message = StallMessage::query()->with('conversation')->firstOrFail();
        $this->assertSame($onlineSeller->id, $message->conversation->farmer_seller_id);
        $this->assertDatabaseHas('orders', [
            'farmer_seller_id' => $cashSeller->id,
            'payment_method' => PaymentMethod::CashOnHandover->value,
        ]);
        $this->assertDatabaseHas('orders', [
            'farmer_seller_id' => $onlineSeller->id,
            'payment_method' => PaymentMethod::OnlineTransfer->value,
        ]);
    }

    public function test_online_payment_is_refused_when_the_seller_opted_out(): void
    {
        $farmer = $this->farmer(['shop_name' => 'Cash Only Stall']);
        $farmer->forceFill(['accepts_online_payment' => false])->save();
        $listing = $this->listingFor($farmer, ['price_per_unit' => 30, 'quantity_available' => 10]);
        $buyer = $this->buyer();
        $this->addToCart($buyer, $listing, 1);

        $this->checkout($buyer, [
            'payments' => [
                ['seller_id' => $farmer->id, 'method' => PaymentMethod::OnlineTransfer->value],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('payments')
            ->assertJsonPath('errors.payments.0', 'Cash Only Stall accepts cash only.');

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, StallMessage::query()->count());
    }

    public function test_a_seller_can_turn_online_payment_off_from_shop_settings(): void
    {
        $farmer = $this->farmer(['shop_name' => 'Nena Stall']);

        $this->asUser($farmer)->patchJson('/api/farmer/shop', [
            'accepts_online_payment' => false,
        ])->assertOk()
            ->assertJsonPath('data.accepts_online_payment', false);

        $this->assertFalse($farmer->fresh()->accepts_online_payment);
    }

    public function test_walk_in_and_a_converted_reservation_stay_cash(): void
    {
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer, [
            'price_per_unit' => 30,
            'quantity_available' => 10,
            'min_order_quantity' => 1,
            'order_step' => 1,
            'available_from' => now()->addDays(3),
            'available_until' => now()->addDays(10),
        ]);
        $buyer = $this->buyer();

        $this->asUser($buyer)->postJson('/api/buyer/reservations', [
            'listing_id' => $listing->id,
            'quantity' => 1,
            'fulfillment_preference' => 'buyer_pickup',
        ])->assertCreated();

        $this->asUser($farmer)->postJson("/api/farmer/listings/{$listing->id}/open")->assertOk();

        $converted = Order::query()->firstOrFail();
        $this->assertNotNull($converted->reservation_id);
        $this->assertSame(PaymentMethod::CashOnHandover->value, $converted->payment_method);

        $walkInListing = $this->listingFor($farmer, ['price_per_unit' => 30, 'quantity_available' => 10]);
        $this->asUser($farmer)->postJson('/api/farmer/walk-in-sales', [
            'listing_id' => $walkInListing->id,
            'quantity' => 1,
            'amount_received' => 30,
        ])->assertCreated();

        $walkIn = Order::query()->whereNull('buyer_id')->firstOrFail();
        $this->assertSame(PaymentMethod::CashOnHandover->value, $walkIn->payment_method);
        $this->assertSame(0, StallMessage::query()->count());
    }

    public function test_a_chat_failure_still_places_the_online_order(): void
    {
        $this->mock(SendStallMessage::class, function ($mock): void {
            $mock->shouldReceive('handle')->once()->andThrow(new RuntimeException('chat down'));
        });

        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer, ['price_per_unit' => 30, 'quantity_available' => 10]);
        $buyer = $this->buyer();
        $this->addToCart($buyer, $listing, 1);

        $this->checkout($buyer, [
            'payments' => [
                ['seller_id' => $farmer->id, 'method' => PaymentMethod::OnlineTransfer->value],
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('orders', [
            'payment_method' => PaymentMethod::OnlineTransfer->value,
        ]);
        $this->assertSame(0, StallMessage::query()->count());
    }

    public function test_a_failed_second_seller_rolls_back_orders_and_qr_messages(): void
    {
        $farm = $this->farm();
        $first = $this->farmer(['email' => 'first@example.com', 'shop_name' => 'First Stall'], $farm);
        $second = $this->farmer(['email' => 'second@example.com', 'shop_name' => 'Second Stall'], $farm);
        $firstListing = $this->listingFor($first, [
            'title' => 'Pechay',
            'price_per_unit' => 30,
            'quantity_available' => 10,
        ]);
        $secondListing = $this->listingFor($second, [
            'title' => 'Sitaw',
            'price_per_unit' => 40,
            'quantity_available' => 10,
        ]);
        $buyer = $this->buyer();
        $this->addToCart($buyer, $firstListing, 1);
        $this->addToCart($buyer, $secondListing, 1);
        $secondListing->forceFill(['quantity_available' => 0])->save();

        $this->checkout($buyer, [
            'payments' => [
                ['seller_id' => $first->id, 'method' => PaymentMethod::OnlineTransfer->value],
                ['seller_id' => $second->id, 'method' => PaymentMethod::OnlineTransfer->value],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('cart');

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, StallMessage::query()->count());
        $this->assertDatabaseCount('in_app_notifications', 0);
    }
}
