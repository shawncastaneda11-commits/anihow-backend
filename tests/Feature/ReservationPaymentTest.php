<?php

namespace Tests\Feature;

use App\Actions\Privacy\ExportOwnDataAction;
use App\Enums\NotificationType;
use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProofStatus;
use App\Enums\ReservationCancellationReason;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Models\InAppNotification;
use App\Models\Listing;
use App\Models\Order;
use App\Models\PaymentProof;
use App\Models\Reservation;
use App\Models\SellerPaymentQr;
use App\Models\StallMessage;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class ReservationPaymentTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_reserving_requires_online_payment_the_proof_flow_and_an_hour_before_opening(): void
    {
        $farmer = $this->farmer(['shop_name' => 'Nena Stall']);
        $listing = $this->upcoming($farmer);
        $buyer = $this->buyer();

        $this->reserve($buyer, $listing)
            ->assertUnprocessable()
            ->assertJsonPath('errors.listing_id.0', "Nena Stall doesn't take reservations yet.");

        $qr = $this->acceptOnlinePayment($farmer, 48);

        $this->reserve($buyer, $listing)
            ->assertUnprocessable()
            ->assertJsonPath('errors.payment_flow.0', 'Update AniHow to reserve.');

        $soon = $this->upcoming($farmer, from: now()->addMinutes(30));
        $this->reserve($buyer, $soon, flow: 'proof')
            ->assertUnprocessable()
            ->assertJsonPath('errors.listing_id.0', 'Reservations closed. You can order once it opens.');

        $this->asUser($buyer)->getJson('/api/buyer/marketplace/'.$soon->id)
            ->assertOk()
            ->assertJsonPath('data.can_reserve', false);

        $opens = now()->addHours(2);
        $held = $this->upcoming($farmer, from: $opens);
        $created = $this->reserve($buyer, $held, flow: 'proof')->assertCreated();
        $reservation = Reservation::query()->findOrFail($created->json('data.id'));

        $this->assertSame(OrderPaymentStatus::AwaitingPayment, $reservation->payment_status);
        $this->assertTrue(
            $reservation->payment_due_at->equalTo($held->fresh()->available_from),
            $reservation->payment_due_at?->toDateTimeString().' vs '.$held->fresh()->available_from?->toDateTimeString(),
        );
        $this->assertSame([$qr->id], $reservation->payment_qr_ids);
        $this->assertSame('reserved', $reservation->paymentEvents()->value('event'));
        $this->asUser($buyer)->getJson('/api/buyer/marketplace/'.$held->id)
            ->assertOk()
            ->assertJsonPath('data.can_reserve', true);
    }

    public function test_a_proof_is_blocked_after_payment_and_a_duplicate_reference_is_shared_with_orders(): void
    {
        [$farmer, $listing, $buyer, $qr, $reservation] = $this->awaiting();

        $this->reserve($buyer, $listing, 2, 'proof')->assertOk();

        $this->sendProof($buyer, $reservation, $qr, 'AB12CD')->assertOk();
        $reservation->refresh();
        $this->assertSame(OrderPaymentStatus::PaymentSent, $reservation->payment_status);
        $this->assertSame(1, StallMessage::query()->where('body', 'like', '%(reservation)%')->count());

        $this->reserve($buyer, $listing, 3, 'proof')
            ->assertUnprocessable()
            ->assertJsonPath('errors.listing_id.0', "You've already paid for this reservation. Message the seller to change it.");

        $this->asUser($buyer)->patchJson("/api/buyer/reservations/{$reservation->id}")
            ->assertUnprocessable()
            ->assertJsonPath('errors.reservation.0', "You've already paid for this reservation. Message the seller to change it.");

        $late = $this->awaiting(hours: 24)[4];
        $late->forceFill(['payment_due_at' => now()->subMinute()])->save();
        $this->sendProof($late->buyer, $late, SellerPaymentQr::query()->findOrFail($late->payment_qr_ids[0]), 'ZZ99YY')
            ->assertUnprocessable()
            ->assertJsonPath('errors.reservation.0', 'The payment time has passed.');

        $order = $this->placeOnlineOrder($farmer, $buyer);
        $this->asUser($buyer)->post("/api/buyer/orders/{$order->id}/payment-proofs", [
            'reference_number' => 'ab12cd',
            'amount' => 30,
            'qr_id' => $qr->id,
        ])->assertUnprocessable()
            ->assertJsonPath('errors.reference_number.0', 'This reference number was already used.');
    }

    public function test_accept_marks_paid_and_converts_once_the_window_is_open(): void
    {
        [$farmer, $listing, $buyer, $qr, $reservation] = $this->awaiting(from: now()->addHours(2));
        $this->sendProof($buyer, $reservation, $qr, 'PAID01')->assertOk();
        $proof = PaymentProof::query()->where('reservation_id', $reservation->id)->firstOrFail();

        $this->asUser($farmer)->patchJson("/api/farmer/reservations/{$reservation->id}/payment-proofs/{$proof->id}", [
            'decision' => 'accept',
        ])->assertOk()
            ->assertJsonPath('data.payment_status', 'paid');

        $this->assertSame(ReservationStatus::Active, $reservation->fresh()->status);
        $this->assertSame(0, Order::query()->count());
        $this->assertSame(1, $this->notices($buyer->id, NotificationType::PaymentConfirmed));

        Carbon::setTestNow($listing->available_from->copy());
        $waiting = $this->upcoming($farmer, from: now()->addDays(2));
        $secondBuyer = $this->buyer();
        $sent = Reservation::query()->findOrFail(
            $this->reserve($secondBuyer, $waiting, flow: 'proof')->assertCreated()->json('data.id'),
        );
        $this->sendProof($secondBuyer, $sent, $qr, 'WAIT01')->assertOk();
        $waiting->forceFill(['available_from' => now()])->save();

        $unpaid = $this->upcoming($farmer, from: now()->addDays(2));
        $third = $this->buyer();
        $awaiting = Reservation::query()->findOrFail(
            $this->reserve($third, $unpaid, flow: 'proof')->assertCreated()->json('data.id'),
        );
        $unpaid->forceFill(['available_from' => now()])->save();

        $cashListing = $this->upcoming($farmer, from: now()->addDays(2));
        $fourth = $this->buyer();
        $cash = Reservation::query()->findOrFail(
            $this->reserve($fourth, $cashListing, flow: 'proof')->assertCreated()->json('data.id'),
        );
        $cash->forceFill([
            'payment_status' => OrderPaymentStatus::NotTracked,
            'payment_due_at' => null,
        ])->save();
        $cashListing->forceFill(['available_from' => now()])->save();

        $this->artisan('reservations:open-due')->assertSuccessful();

        $paidOrder = Order::query()->where('reservation_id', $reservation->id)->firstOrFail();
        $this->assertSame(ReservationStatus::Converted, $reservation->fresh()->status);
        $this->assertSame(PaymentMethod::OnlineTransfer->value, $paidOrder->payment_method);
        $this->assertSame(OrderPaymentStatus::Paid, $paidOrder->payment_status);
        $this->assertTrue($paidOrder->paid_at->equalTo($reservation->fresh()->paid_at));
        $this->assertNull($paidOrder->payment_due_at);
        $this->assertSame($reservation->payment_qr_ids, $paidOrder->payment_qr_ids);
        $this->assertSame($paidOrder->id, $proof->fresh()->order_id);
        $this->assertDatabaseHas('order_payment_events', [
            'order_id' => $paidOrder->id,
            'event' => 'paid_by_reservation',
        ]);

        $this->asUser($farmer)->patchJson("/api/farmer/orders/{$paidOrder->id}/confirm")->assertOk();
        $this->asUser($farmer)->patchJson("/api/farmer/orders/{$paidOrder->id}/ready")->assertOk();
        $this->asUser($farmer)->patchJson("/api/farmer/orders/{$paidOrder->id}/complete", [])->assertOk();
        $this->assertSame($proof->amount, $paidOrder->fresh()->amount_received);

        $this->assertSame(ReservationStatus::Active, $sent->fresh()->status);
        $this->assertSame(OrderPaymentStatus::PaymentSent, $sent->fresh()->payment_status);
        $this->assertNull(Order::query()->where('reservation_id', $sent->id)->first());

        $this->assertSame(ReservationStatus::Cancelled, $awaiting->fresh()->status);
        $this->assertSame(ReservationCancellationReason::PaymentExpired, $awaiting->fresh()->cancellation_reason);

        $cashOrder = Order::query()->where('reservation_id', $cash->id)->firstOrFail();
        $this->assertSame(PaymentMethod::CashOnHandover->value, $cashOrder->payment_method);
        $this->assertNull($cashOrder->payment_status);
    }

    public function test_accepting_after_opening_converts_immediately_and_reject_extends_the_due_time(): void
    {
        [$farmer, $listing, $buyer, $qr, $reservation] = $this->awaiting(from: now()->addHours(2));
        $this->sendProof($buyer, $reservation, $qr, 'OPEN01')->assertOk();
        $proof = PaymentProof::query()->where('reservation_id', $reservation->id)->firstOrFail();

        Carbon::setTestNow($listing->available_from->copy());

        $this->asUser($farmer)->patchJson("/api/farmer/reservations/{$reservation->id}/payment-proofs/{$proof->id}", [
            'decision' => 'accept',
        ])->assertOk();

        $this->assertSame(ReservationStatus::Converted, $reservation->fresh()->status);
        $this->assertSame(OrderPaymentStatus::Paid, Order::query()->where('reservation_id', $reservation->id)->value('payment_status'));

        [$farmer, $listing, $buyer, $qr, $reservation] = $this->awaiting(from: now()->addDays(3));
        $this->sendProof($buyer, $reservation, $qr, 'NOPE01')->assertOk();
        $proof = PaymentProof::query()->where('reservation_id', $reservation->id)->firstOrFail();
        $reservation->forceFill(['payment_due_at' => now()->addMinutes(10), 'payment_reminded_at' => now()])->save();

        $this->asUser($farmer)->patchJson("/api/farmer/reservations/{$reservation->id}/payment-proofs/{$proof->id}", [
            'decision' => 'reject',
            'reason' => 'not_received',
        ])->assertOk()
            ->assertJsonPath('data.payment_status', 'awaiting_payment');

        $fresh = $reservation->fresh();
        $this->assertTrue($fresh->payment_due_at->equalTo(now()->addHour()));
        $this->assertNull($fresh->payment_reminded_at);
    }

    public function test_upkeep_voids_an_unpaid_reservation_reminds_once_and_reminds_the_seller(): void
    {
        [$farmer, $listing, $buyer, $qr, $reservation] = $this->awaiting(from: now()->addDays(3), quantity: 5);
        $listing->forceFill(['quantity_available' => 5])->save();
        $reservation->forceFill([
            'created_at' => now()->subHours(3),
            'payment_due_at' => now()->addMinutes(30),
        ])->save();

        $this->artisan('payments:upkeep')->assertSuccessful();
        $this->artisan('payments:upkeep')->assertSuccessful();

        $this->assertSame(1, $this->notices($buyer->id, NotificationType::PaymentDueSoon));
        $this->assertSame(ReservationStatus::Active, $reservation->fresh()->status);

        $reservation->forceFill(['payment_due_at' => now()->subMinute()])->save();
        $this->artisan('payments:upkeep')->assertSuccessful();

        $this->assertSame(ReservationStatus::Cancelled, $reservation->fresh()->status);
        $this->assertSame(ReservationCancellationReason::PaymentExpired, $reservation->fresh()->cancellation_reason);
        $this->assertSame(1, $this->notices($buyer->id, NotificationType::PaymentExpired));
        $this->assertSame(1, $this->notices($farmer->id, NotificationType::PaymentExpired));
        $this->assertSame(0, $this->notices($buyer->id, NotificationType::ReservationCancelled));

        $next = $this->buyer();
        $this->reserve($next, $listing, 5, 'proof')->assertCreated();

        $pending = $this->awaiting()[4];
        $this->sendProof($pending->buyer, $pending, SellerPaymentQr::query()->findOrFail($pending->payment_qr_ids[0]), 'SLOW01')->assertOk();
        PaymentProof::query()->where('reservation_id', $pending->id)->update(['created_at' => now()->subHours(13)]);

        $this->artisan('payments:upkeep')->assertSuccessful();
        $this->artisan('payments:upkeep')->assertSuccessful();
        $this->assertSame(1, $this->notices($pending->farmer_seller_id, NotificationType::PaymentCheckReminder));
    }

    public function test_a_paid_reservation_becomes_refund_due_on_shortfall_seller_cancel_and_listing_removal(): void
    {
        [$farmer, $listing, $buyer, $qr, $reservation] = $this->paidReservation(quantity: 6);
        $listing->forceFill(['quantity_available' => 2])->save();
        Carbon::setTestNow($listing->available_from->copy());
        $this->artisan('reservations:open-due')->assertSuccessful();

        $this->assertSame(ReservationCancellationReason::HarvestShortfall, $reservation->fresh()->cancellation_reason);
        $this->assertSame(OrderPaymentStatus::RefundDue, $reservation->fresh()->payment_status);
        $this->assertSame(1, $this->notices($buyer->id, NotificationType::RefundDue));
        $this->assertSame(1, $this->notices($farmer->id, NotificationType::RefundDue));

        $this->asUser($farmer)->patchJson("/api/farmer/reservations/{$reservation->id}/refund", [])
            ->assertUnprocessable();
        $this->asUser($farmer)->patchJson("/api/farmer/reservations/{$reservation->id}/refund", [
            'refund_reference' => 'rf11aa',
        ])->assertOk()
            ->assertJsonPath('data.payment_status', 'refunded')
            ->assertJsonPath('data.refund_reference', 'RF11AA');

        [$farmer, $listing, $buyer, $qr, $reservation] = $this->awaiting();
        $this->sendProof($buyer, $reservation, $qr, 'SENT99')->assertOk();
        $this->asUser($farmer)->patchJson("/api/farmer/listings/{$listing->id}/reservations/{$reservation->id}", [
            'note' => 'Crop failed',
        ])->assertOk();
        $this->assertSame(OrderPaymentStatus::RefundDue, $reservation->fresh()->payment_status);
        $this->assertSame(PaymentProofStatus::Rejected, PaymentProof::query()->where('reservation_id', $reservation->id)->firstOrFail()->status);
        $this->assertSame(
            'Reservation cancelled',
            PaymentProof::query()->where('reservation_id', $reservation->id)->value('rejection_note'),
        );

        [$farmer, $listing, $buyer, $qr, $reservation] = $this->paidReservation();
        $this->asUser($farmer)->deleteJson("/api/farmer/listings/{$listing->id}", [
            'confirm_cancel_reservations' => true,
        ])->assertOk();
        $this->assertSame(OrderPaymentStatus::RefundDue, $reservation->fresh()->payment_status);
        $this->assertSame(ReservationCancellationReason::ListingRemoved, $reservation->fresh()->cancellation_reason);
    }

    public function test_online_payment_qr_deletion_and_account_deletion_wait_for_tracked_reservations(): void
    {
        [$farmer, $listing, $buyer, $qr, $reservation] = $this->awaiting();

        $this->asUser($farmer)->patchJson('/api/farmer/shop', [
            'accepts_online_payment' => false,
        ])->assertUnprocessable()
            ->assertJsonPath('errors.accepts_online_payment.0', 'Finish or cancel your reservations first.');

        $this->asUser($farmer)->delete("/api/farmer/payment-qrs/{$qr->id}")
            ->assertUnprocessable()
            ->assertJsonPath('errors.qr.0', 'Finish or cancel your reservations first.');

        $this->sendProof($buyer, $reservation, $qr, 'HOLD01')->assertOk();

        $this->asUser($buyer)->postJson('/api/auth/user/deletion-request')
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'Settle your pending payments and refunds first.');

        $this->asUser($farmer)->postJson('/api/auth/user/deletion-request')
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'Settle your pending payments and refunds first.');
    }

    public function test_the_seller_payment_list_qr_access_and_export_include_reservations(): void
    {
        [$farmer, $listing, $buyer, $qr, $reservation] = $this->awaiting();
        $this->sendProof($buyer, $reservation, $qr, 'LIST01')->assertOk();

        $order = $this->placeOnlineOrder($farmer, $this->buyer());
        $this->asUser(User::query()->findOrFail($order->buyer_id))->post("/api/buyer/orders/{$order->id}/payment-proofs", [
            'reference_number' => 'LIST02',
            'amount' => 30,
            'qr_id' => $qr->id,
        ])->assertOk();

        $list = $this->asUser($farmer)->getJson('/api/farmer/payments?status=to_check')->assertOk();
        $list->assertJsonStructure([
            'data' => [[
                'kind',
                'id',
                'buyer_name',
                'title',
                'items',
                'amount',
                'wallet',
                'reference',
                'sent_at',
                'paid_at',
                'status_at',
                'order_id',
                'reservation_id',
            ]],
        ]);
        $kinds = collect($list->json('data'))->pluck('kind')->all();
        $this->assertSame(['order', 'reservation'], $kinds);
        $this->assertSame('LIST02', $list->json('data.0.reference'));
        $this->assertSame($listing->title, $list->json('data.1.title'));
        $this->assertSame($reservation->id, $list->json('data.1.reservation_id'));

        Storage::fake('local');
        Storage::disk('local')->put($qr->image_path, 'img');
        $this->asUser($buyer)->get('/api/payment-qrs/'.$qr->id.'/image')->assertOk();
        $this->asUser($this->buyer())->get('/api/payment-qrs/'.$qr->id.'/image')->assertForbidden();

        $proof = PaymentProof::query()->where('reservation_id', $reservation->id)->firstOrFail();
        Storage::disk('local')->put('payment-proofs/reservations/shot.jpg', 'img');
        $proof->forceFill(['screenshot_path' => 'payment-proofs/reservations/shot.jpg'])->save();
        $this->asUser($buyer)->get('/api/payment-proofs/'.$proof->id.'/screenshot')->assertOk();
        $this->asUser($this->buyer())->get('/api/payment-proofs/'.$proof->id.'/screenshot')->assertForbidden();

        $export = app(ExportOwnDataAction::class)->handle($buyer);
        $row = collect($export['payment_proofs'])->firstWhere('reservation_id', $reservation->id);
        $this->assertSame('LIST01', $row['reference_number']);

        $admin = User::factory()->create();
        $admin->syncRoles(Role::SuperAdmin);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::actingAs($admin)
            ->test(ListPayments::class)
            ->assertCanSeeTableRecords([$proof])
            ->assertSee('Reservation');
    }

    /**
     * @return array{0: User, 1: Listing, 2: User, 3: SellerPaymentQr, 4: Reservation}
     */
    private function awaiting(?Carbon $from = null, int $hours = 24, float $quantity = 1): array
    {
        $farmer = $this->farmer();
        $qr = $this->acceptOnlinePayment($farmer, $hours);
        $listing = $this->upcoming($farmer, $from, max($quantity, 10));
        $buyer = $this->buyer();
        $id = $this->reserve($buyer, $listing, $quantity, 'proof')->assertCreated()->json('data.id');

        return [$farmer, $listing, $buyer, $qr, Reservation::query()->findOrFail($id)];
    }

    /**
     * @return array{0: User, 1: Listing, 2: User, 3: SellerPaymentQr, 4: Reservation}
     */
    private function paidReservation(float $quantity = 1): array
    {
        [$farmer, $listing, $buyer, $qr, $reservation] = $this->awaiting(quantity: $quantity);
        $this->sendProof($buyer, $reservation, $qr, strtoupper(fake()->bothify('??????')))->assertOk();
        $proof = PaymentProof::query()->where('reservation_id', $reservation->id)->firstOrFail();
        $this->asUser($farmer)->patchJson("/api/farmer/reservations/{$reservation->id}/payment-proofs/{$proof->id}", [
            'decision' => 'accept',
        ])->assertOk();

        return [$farmer, $listing, $buyer, $qr, $reservation->fresh()];
    }

    private function upcoming(User $farmer, ?Carbon $from = null, float $quantity = 10): Listing
    {
        return $this->listingFor($farmer, [
            'price_per_unit' => 30,
            'quantity_available' => $quantity,
            'min_order_quantity' => 1,
            'order_step' => 1,
            'available_from' => $from ?? now()->addDays(3),
            'available_until' => ($from ?? now()->addDays(3))->copy()->addDays(7),
        ]);
    }

    private function reserve(User $buyer, Listing $listing, float $quantity = 1, ?string $flow = null): TestResponse
    {
        return $this->asUser($buyer)->postJson('/api/buyer/reservations', [
            'listing_id' => $listing->id,
            'quantity' => $quantity,
            'fulfillment_preference' => 'buyer_pickup',
            'payment_flow' => $flow,
        ]);
    }

    private function sendProof(User $buyer, Reservation $reservation, SellerPaymentQr $qr, string $reference): TestResponse
    {
        return $this->asUser($buyer)->postJson("/api/buyer/reservations/{$reservation->id}/payment-proofs", [
            'reference_number' => $reference,
            'amount' => 30,
            'qr_id' => $qr->id,
        ]);
    }

    private function placeOnlineOrder(User $farmer, User $buyer): Order
    {
        $listing = $this->listingFor($farmer, ['price_per_unit' => 30, 'quantity_available' => 10]);
        $this->addToCart($buyer, $listing, 1);
        $id = $this->checkout($buyer, [
            'payment_flow' => 'proof',
            'payments' => [
                ['seller_id' => $farmer->id, 'method' => PaymentMethod::OnlineTransfer->value],
            ],
        ])->assertCreated()->json('data.0.id');

        return Order::query()->findOrFail($id);
    }

    private function notices(int $userId, NotificationType $type): int
    {
        return InAppNotification::query()
            ->where('user_id', $userId)
            ->where('type', $type)
            ->count();
    }
}
