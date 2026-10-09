<?php

namespace Tests\Feature;

use App\Actions\Privacy\ExportOwnDataAction;
use App\Actions\Reports\ResolveReportAction;
use App\Enums\CancellationReason;
use App\Enums\NotificationType;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentProofStatus;
use App\Enums\ReportReason;
use App\Enums\Role;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Reports\Tables\ReportsTable;
use App\Models\Farm;
use App\Models\InAppNotification;
use App\Models\Listing;
use App\Models\Order;
use App\Models\OrderPaymentEvent;
use App\Models\OrderStatusHistory;
use App\Models\PaymentProof;
use App\Models\Report;
use App\Models\SellerPaymentQr;
use App\Models\StallMessage;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

class OnlinePaymentTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
    }

    public function test_a_seller_can_save_three_qr_codes_and_not_a_fourth(): void
    {
        $farmer = $this->farmer();

        foreach (['1111', '2222', '3333'] as $last4) {
            $this->asUser($farmer)->post('/api/farmer/payment-qrs', $this->qrPayload($last4))->assertCreated();
        }

        $this->asUser($farmer)->post('/api/farmer/payment-qrs', $this->qrPayload('4444'))
            ->assertUnprocessable()
            ->assertJsonPath('errors.image.0', 'You can save up to 3 QR codes.');

        $this->assertSame(3, SellerPaymentQr::query()->count());
    }

    public function test_deleting_the_last_qr_is_blocked_while_a_buyer_is_paying_and_turns_online_off_otherwise(): void
    {
        $farmer = $this->farmer(['shop_name' => 'Nena Stall']);
        $qr = $this->saveQr($farmer);
        $this->turnOnlineOn($farmer);
        $order = $this->placeOnline($farmer);

        $this->asUser($farmer)->delete("/api/farmer/payment-qrs/{$qr->id}")
            ->assertUnprocessable()
            ->assertJsonPath('errors.qr.0', "You can't delete your last QR while a buyer is still paying.");
        $this->assertNull($qr->fresh()->deleted_at);
        $this->assertTrue($farmer->fresh()->acceptsOnlinePayment());

        $order->forceFill(['payment_status' => OrderPaymentStatus::Paid])->save();

        $this->asUser($farmer)->delete("/api/farmer/payment-qrs/{$qr->id}")->assertOk();
        $this->assertNotNull($qr->fresh()->deleted_at);
        $this->assertFalse($farmer->fresh()->accepts_online_payment);
    }

    public function test_online_payment_cannot_be_turned_on_without_a_qr(): void
    {
        $farmer = $this->farmer();

        $this->asUser($farmer)->patchJson('/api/farmer/shop', [
            'accepts_online_payment' => true,
        ])->assertUnprocessable()
            ->assertJsonPath('errors.accepts_online_payment.0', 'Add a QR code first.');

        $this->saveQr($farmer);

        $this->asUser($farmer)->patchJson('/api/farmer/shop', [
            'accepts_online_payment' => true,
            'payment_time_limit_hours' => 6,
        ])->assertOk()
            ->assertJsonPath('data.accepts_online_payment', true)
            ->assertJsonPath('data.payment_time_limit_hours', 6)
            ->assertJsonPath('data.payment_qrs.0.account_last4', '1234');
    }

    public function test_a_public_shop_page_never_includes_qr_data(): void
    {
        $farmer = $this->farmer(['shop_name' => 'Nena Stall']);
        $this->saveQr($farmer);
        $buyer = $this->buyer();

        $this->asUser($buyer)->get("/api/buyer/shops/{$farmer->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.payment_qrs')
            ->assertJsonMissingPath('data.payment_time_limit_hours');

        $this->assertStringNotContainsString(
            '1234',
            $this->asUser($buyer)->get("/api/buyer/shops/{$farmer->id}")->getContent(),
        );
    }

    public function test_qr_image_access_is_limited_to_the_owner_admin_and_the_paying_buyer(): void
    {
        $farmer = $this->farmer();
        $qr = $this->saveQr($farmer);
        $this->turnOnlineOn($farmer);
        $order = $this->placeOnline($farmer);
        $buyer = User::query()->findOrFail($order->buyer_id);
        $other = $this->buyer(['email' => 'other@example.com']);
        $admin = $this->staff(Role::SuperAdmin);

        $image = $this->asUser($farmer)->get("/api/payment-qrs/{$qr->id}/image")->assertOk();
        $cache = (string) $image->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cache);
        $this->assertStringContainsString('no-store', $cache);
        $this->asUser($admin)->get("/api/payment-qrs/{$qr->id}/image")->assertOk();
        $this->asUser($buyer)->get("/api/payment-qrs/{$qr->id}/image")->assertOk();
        $this->asUser($other)->get("/api/payment-qrs/{$qr->id}/image")->assertForbidden();

        $spare = $this->saveQr($farmer, '9999');
        $qr->delete();

        $this->asUser($buyer)->get("/api/payment-qrs/{$qr->id}/image")->assertOk();
        $this->asUser($buyer)->get("/api/payment-qrs/{$spare->id}/image")->assertForbidden();
    }

    public function test_checkout_refuses_online_without_a_qr_or_without_the_proof_flow(): void
    {
        $farmer = $this->farmer(['shop_name' => 'Cash Only Stall']);
        $listing = $this->listingFor($farmer, ['price_per_unit' => 30, 'quantity_available' => 10]);
        $buyer = $this->buyer();
        $this->addToCart($buyer, $listing, 1);

        $this->checkout($buyer, [
            'payments' => [
                ['seller_id' => $farmer->id, 'method' => PaymentMethod::OnlineTransfer->value],
            ],
        ])->assertUnprocessable()
            ->assertJsonPath('errors.payments.0', 'Update AniHow to pay online.');

        $this->checkout($buyer, [
            'payment_flow' => 'proof',
            'payments' => [
                ['seller_id' => $farmer->id, 'method' => PaymentMethod::OnlineTransfer->value],
            ],
        ])->assertUnprocessable()
            ->assertJsonPath('errors.payments.0', 'Cash Only Stall accepts cash only.');

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, StallMessage::query()->count());
    }

    public function test_an_online_order_snapshots_the_due_time_and_qr_codes(): void
    {
        $farmer = $this->farmer();
        $qr = $this->saveQr($farmer);
        $this->turnOnlineOn($farmer, 3);

        $order = $this->placeOnline($farmer);

        $this->assertSame(OrderPaymentStatus::AwaitingPayment, $order->payment_status);
        $this->assertEqualsWithDelta(now()->addHours(3)->timestamp, $order->payment_due_at->timestamp, 5);
        $this->assertSame([$qr->id], $order->payment_qr_ids);
        $this->assertDatabaseHas('order_payment_events', [
            'order_id' => $order->id,
            'event' => 'placed_online',
        ]);
        $this->assertSame(0, StallMessage::query()->count());
        $this->assertSame(0, OrderStatusHistory::query()->where('note', 'like', '%proof%')->count());
    }

    public function test_proof_rules_and_a_normalized_duplicate_reference(): void
    {
        $farmer = $this->farmer();
        $first = $this->saveQr($farmer);
        $this->turnOnlineOn($farmer);
        $order = $this->placeOnline($farmer);
        $buyer = User::query()->findOrFail($order->buyer_id);

        $this->asUser($buyer)->post("/api/buyer/orders/{$order->id}/payment-proofs", [
            'reference_number' => 'ab 12-cd',
            'amount' => 30,
            'qr_id' => $first->id,
        ])->assertOk();

        $proof = PaymentProof::query()->firstOrFail();
        $this->assertSame('AB12CD', $proof->reference_number);
        $this->assertSame(OrderPaymentStatus::PaymentSent, $order->fresh()->payment_status);
        $this->assertDatabaseHas('order_payment_events', ['event' => 'proof_sent']);
        $this->assertSame(0, OrderStatusHistory::query()->count());
        $message = StallMessage::query()->firstOrFail();
        $this->assertSame($buyer->id, $message->user_id);
        $this->assertSame('Sent ₱30.00 via GCash, ref AB12CD', $message->body);

        $late = $this->placeOnline($farmer);
        $late->forceFill(['payment_due_at' => now()->subMinute()])->save();
        $this->asUser($buyer)->post("/api/buyer/orders/{$late->id}/payment-proofs", [
            'reference_number' => 'LATE1234',
            'amount' => 30,
            'qr_id' => $first->id,
        ])->assertUnprocessable()
            ->assertJsonPath('errors.order.0', 'The payment time has passed.');

        $again = $this->placeOnline($farmer);
        $this->asUser($buyer)->post("/api/buyer/orders/{$again->id}/payment-proofs", [
            'reference_number' => 'AB12CD',
            'amount' => 30,
            'qr_id' => $first->id,
        ])->assertUnprocessable()
            ->assertJsonPath('errors.reference_number.0', 'This reference number was already used.');

        $fresh = $this->placeOnline($farmer);
        $other = $this->saveQr($farmer, '5678');
        $this->asUser($buyer)->post("/api/buyer/orders/{$fresh->id}/payment-proofs", [
            'reference_number' => 'NEWREF99',
            'amount' => 30,
            'qr_id' => $other->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('qr_id');
    }

    public function test_accepting_a_proof_lets_the_seller_complete_without_typing_the_amount(): void
    {
        [$farmer, $order, $proof] = $this->sentProof();

        $this->asUser($farmer)->patchJson("/api/farmer/orders/{$order->id}/payment-proofs/{$proof->id}", [
            'decision' => 'accept',
        ])->assertOk()
            ->assertJsonPath('data.payment_status', OrderPaymentStatus::Paid->value);

        $this->assertNotNull($order->fresh()->paid_at);
        $this->assertSame('Payment received. Thank you!', StallMessage::query()->latest('id')->value('body'));

        $this->asUser($farmer)->patchJson("/api/farmer/orders/{$order->id}/confirm")->assertOk();
        $this->asUser($farmer)->patchJson("/api/farmer/orders/{$order->id}/ready")->assertOk();
        $this->asUser($farmer)->patchJson("/api/farmer/orders/{$order->id}/complete", [])
            ->assertOk()
            ->assertJsonPath('data.amount_received', 30);
    }

    public function test_rejecting_a_proof_reopens_payment_for_at_least_an_hour(): void
    {
        [$farmer, $order, $proof] = $this->sentProof();
        $order->forceFill(['payment_due_at' => now()->subMinutes(10)])->save();

        $this->asUser($farmer)->patchJson("/api/farmer/orders/{$order->id}/payment-proofs/{$proof->id}", [
            'decision' => 'reject',
            'reason' => 'wrong_amount',
        ])->assertOk()
            ->assertJsonPath('data.payment_status', OrderPaymentStatus::AwaitingPayment->value);

        $this->assertTrue($order->fresh()->payment_due_at->greaterThanOrEqualTo(now()->addHour()->subSeconds(5)));
        $this->assertSame(PaymentProofStatus::Rejected, $proof->fresh()->status);
        $this->assertSame(1, $this->notices($order->buyer_id, NotificationType::PaymentRejected));
    }

    public function test_ready_and_completed_wait_for_payment_but_confirm_does_not(): void
    {
        $farmer = $this->farmer();
        $this->saveQr($farmer);
        $this->turnOnlineOn($farmer);
        $order = $this->placeOnline($farmer);

        $this->asUser($farmer)->patchJson("/api/farmer/orders/{$order->id}/confirm")->assertOk();
        $this->asUser($farmer)->patchJson("/api/farmer/orders/{$order->id}/ready")
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', "Wait for the buyer's payment to be confirmed.");
    }

    public function test_untracked_orders_still_require_the_cash_counted_at_handover(): void
    {
        $farmer = $this->farmer();
        $listing = $this->listingFor($farmer, ['price_per_unit' => 30, 'quantity_available' => 10]);
        $cash = $this->placeOrder($this->buyer(), $listing, 1);

        $this->asUser($farmer)->patchJson("/api/farmer/orders/{$cash->id}/confirm")->assertOk();
        $this->asUser($farmer)->patchJson("/api/farmer/orders/{$cash->id}/ready")->assertOk();
        $this->asUser($farmer)->patchJson("/api/farmer/orders/{$cash->id}/complete", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount_received');
        $this->asUser($farmer)->patchJson("/api/farmer/orders/{$cash->id}/complete", [
            'amount_received' => 30,
        ])->assertOk()->assertJsonPath('data.amount_received', 30);

        $legacy = $this->placeOrder($this->buyer(['email' => 'legacy@example.com']), $listing, 1);
        $legacy->forceFill([
            'payment_method' => PaymentMethod::OnlineTransfer,
            'payment_status' => OrderPaymentStatus::NotTracked,
        ])->save();
        $this->asUser($farmer)->patchJson("/api/farmer/orders/{$legacy->id}/confirm")->assertOk();
        $this->asUser($farmer)->patchJson("/api/farmer/orders/{$legacy->id}/ready")->assertOk();
        $this->asUser($farmer)->patchJson("/api/farmer/orders/{$legacy->id}/complete", [
            'amount_received' => 28,
        ])->assertOk()->assertJsonPath('data.amount_received', 28);
    }

    public function test_a_buyer_cannot_cancel_after_sending_proof_and_a_paid_cancel_becomes_a_refund(): void
    {
        [$farmer, $order, $proof] = $this->sentProof();
        $buyer = User::query()->findOrFail($order->buyer_id);

        $this->asUser($buyer)->patchJson("/api/buyer/orders/{$order->id}/cancel")
            ->assertForbidden();

        $this->asUser($farmer)->patchJson("/api/farmer/orders/{$order->id}/cancel", [
            'reason' => 'no_show',
        ])->assertOk();

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame(OrderPaymentStatus::RefundDue, $order->payment_status);
        $this->assertSame(PaymentProofStatus::Rejected, $proof->fresh()->status);
        $this->assertSame('Order cancelled', $proof->fresh()->rejection_note);
        $this->assertSame(1, $this->notices($buyer->id, NotificationType::RefundDue));
        $this->assertSame(1, $this->notices($farmer->id, NotificationType::RefundDue));

        $this->asUser($farmer)->patchJson("/api/farmer/orders/{$order->id}/refund", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('refund_reference');

        $this->asUser($farmer)->patchJson("/api/farmer/orders/{$order->id}/refund", [
            'refund_reference' => 'rf 99-01',
        ])->assertOk()
            ->assertJsonPath('data.payment_status', OrderPaymentStatus::Refunded->value)
            ->assertJsonPath('data.refund_reference', 'RF9901');
    }

    public function test_the_stale_sweep_turns_a_paid_unanswered_order_into_a_refund(): void
    {
        [$farmer, $order] = $this->sentProof();
        $order->forceFill([
            'payment_status' => OrderPaymentStatus::Paid,
            'paid_at' => now(),
            'created_at' => now()->subHours(49),
        ])->save();
        $listingId = $order->items()->value('listing_id');

        $this->artisan('orders:sweep-stale')->assertSuccessful();

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame(CancellationReason::SellerUnresponsive, $order->cancellation_reason);
        $this->assertSame(OrderPaymentStatus::RefundDue, $order->payment_status);
        $this->assertSame(0.0, (float) $order->items()->first()->listing()->value('quantity_held') ?: (float) Listing::query()->find($listingId)->quantity_held);
    }

    public function test_payment_upkeep_expires_unpaid_placed_and_confirmed_orders_and_reminds_once(): void
    {
        $farmer = $this->farmer();
        $this->saveQr($farmer);
        $this->turnOnlineOn($farmer);
        $placed = $this->placeOnline($farmer);
        $confirmed = $this->placeOnline($farmer);
        $buyer = User::query()->findOrFail($placed->buyer_id);
        $placedListing = $placed->items()->first()->listing;
        $confirmedListing = $confirmed->items()->first()->listing;
        $heldBefore = (float) $placedListing->quantity_held;
        $availableBefore = (float) $confirmedListing->quantity_available;

        $this->asUser($farmer)->patchJson("/api/farmer/orders/{$confirmed->id}/confirm")->assertOk();
        $placed->forceFill(['payment_due_at' => now()->subMinute()])->save();
        $confirmed->forceFill(['payment_due_at' => now()->subMinute()])->save();

        $this->artisan('payments:upkeep')->assertSuccessful();

        $this->assertSame(OrderStatus::Cancelled, $placed->fresh()->status);
        $this->assertSame(OrderStatus::Cancelled, $confirmed->fresh()->status);
        $this->assertSame(CancellationReason::PaymentExpired, $placed->fresh()->cancellation_reason);
        $this->assertLessThan($heldBefore, (float) $placedListing->fresh()->quantity_held);
        $this->assertGreaterThan($availableBefore - 1, (float) $confirmedListing->fresh()->quantity_available);
        $this->assertSame(2, $this->notices($buyer->id, NotificationType::PaymentExpired));
        $this->assertSame(2, $this->notices($farmer->id, NotificationType::PaymentExpired));
        $this->assertSame(0, $this->notices($buyer->id, NotificationType::OrderCancelled));
        $this->assertDatabaseHas('order_payment_events', [
            'order_id' => $placed->id,
            'event' => 'expired',
        ]);

        $waiting = $this->placeOnline($farmer);
        $waiting->forceFill(['payment_due_at' => now()->addMinutes(30)])->save();
        $this->artisan('payments:upkeep')->assertSuccessful();
        $this->artisan('payments:upkeep')->assertSuccessful();
        $this->assertSame(1, $this->notices($buyer->id, NotificationType::PaymentDueSoon));

        $proofOrder = $this->placeOnline($farmer);
        $this->asUser($buyer)->post("/api/buyer/orders/{$proofOrder->id}/payment-proofs", [
            'reference_number' => 'REMIND01',
            'amount' => 30,
            'qr_id' => $proofOrder->payment_qr_ids[0],
        ])->assertOk();
        $proof = PaymentProof::query()->where('order_id', $proofOrder->id)->firstOrFail();
        $proof->forceFill(['created_at' => now()->subHours(13)])->save();
        $this->artisan('payments:upkeep')->assertSuccessful();
        $this->assertNotNull($proof->fresh()->reminder_12h_at);
        $this->assertNull($proof->fresh()->reminder_24h_at);
        $proof->forceFill(['created_at' => now()->subHours(25)])->save();
        $this->artisan('payments:upkeep')->assertSuccessful();
        $this->artisan('payments:upkeep')->assertSuccessful();
        $this->assertNotNull($proof->fresh()->reminder_24h_at);
        $this->assertSame(2, $this->notices($farmer->id, NotificationType::PaymentCheckReminder));
    }

    public function test_settled_proof_files_are_deleted_after_ninety_days_and_the_reference_stays(): void
    {
        [$farmer, $order, $proof] = $this->sentProof();
        Storage::disk('local')->put('payment-proofs/old.jpg', 'image-bytes');
        $proof->forceFill(['screenshot_path' => 'payment-proofs/old.jpg'])->save();
        $order->forceFill([
            'status' => OrderStatus::Completed,
            'payment_status' => OrderPaymentStatus::Paid,
            'completed_at' => now()->subDays(91),
        ])->save();

        $this->artisan('payments:purge-proof-files')->assertSuccessful();

        $proof->refresh();
        $this->assertNotNull($proof->screenshot_deleted_at);
        $this->assertSame('AB12CD', $proof->reference_number);
        $this->assertFalse(Storage::disk('local')->exists('payment-proofs/old.jpg'));
    }

    public function test_only_the_order_parties_can_report_a_payment_problem_and_the_cms_does_not_take_it_down(): void
    {
        [$farmer, $order] = $this->sentProof();
        $buyer = User::query()->findOrFail($order->buyer_id);
        $stranger = $this->buyer(['email' => 'stranger@example.com']);
        $admin = $this->staff(Role::SuperAdmin);

        $this->asUser($stranger)->postJson('/api/reports', [
            'target_type' => 'order',
            'target_id' => $order->id,
            'reason' => ReportReason::PaymentProblem->value,
        ])->assertForbidden();

        $this->asUser($buyer)->postJson('/api/reports', [
            'target_type' => 'order',
            'target_id' => $order->id,
            'reason' => ReportReason::SpamOrFake->value,
        ])->assertUnprocessable()->assertJsonValidationErrors('reason');

        $report = $this->asUser($buyer)->postJson('/api/reports', [
            'target_type' => 'order',
            'target_id' => $order->id,
            'reason' => ReportReason::PaymentProblem->value,
        ])->assertCreated()->json('data.id');

        $this->asUser($farmer)->postJson('/api/reports', [
            'target_type' => 'order',
            'target_id' => $order->id,
            'reason' => ReportReason::PaymentProblem->value,
        ])->assertCreated();

        $model = Report::query()->findOrFail($report);
        $this->assertFalse(ReportsTable::offersModeration($model));
        $this->assertStringContainsString($order->order_number, ReportsTable::targetLabel($model));
        $this->assertStringContainsString('Payment sent', ReportsTable::targetLabel($model));

        app(ResolveReportAction::class)->handle($model, $admin, 'Noted.', true, 'Take it down.');
        $this->assertSame(OrderStatus::Placed, $order->fresh()->status);
    }

    public function test_account_deletion_is_blocked_while_a_refund_is_due(): void
    {
        [$farmer, $order] = $this->sentProof();
        $order->forceFill([
            'status' => OrderStatus::Cancelled,
            'payment_status' => OrderPaymentStatus::RefundDue,
            'cancelled_at' => now(),
        ])->save();

        $this->asUser($farmer)->postJson('/api/auth/user/deletion-request')
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'Settle your pending payments and refunds first.');
    }

    public function test_the_payments_resource_is_super_admin_only_and_export_includes_proofs(): void
    {
        [$farmer, $order, $proof] = $this->sentProof();
        $admin = $this->staff(Role::SuperAdmin);
        $editor = $this->staff(Role::ContentEditor, Farm::factory()->create());

        $this->flushHeaders();
        $this->actingAs($admin, 'web')->get('/admin/payments')->assertOk();

        $this->actingAs($editor, 'web');
        $this->assertFalse(PaymentResource::canAccess());
        $this->get('/admin/payments')->assertForbidden();

        $buyer = User::query()->findOrFail($order->buyer_id);
        $export = app(ExportOwnDataAction::class)->handle($buyer);
        $this->assertSame($proof->reference_number, $export['payment_proofs'][0]['reference_number']);

        $sellerExport = app(ExportOwnDataAction::class)->handle($farmer);
        $this->assertSame('1234', $sellerExport['payment_qrs'][0]['account_last4']);
        $this->assertSame($proof->reference_number, $sellerExport['payment_proofs'][0]['reference_number']);
        $this->assertSame(0, OrderPaymentEvent::query()->whereIn('event', OrderStatusHistory::query()->pluck('note'))->count());
    }

    /**
     * @return array{0: User, 1: Order, 2: PaymentProof}
     */
    private function sentProof(): array
    {
        $farmer = $this->farmer();
        $qr = $this->saveQr($farmer);
        $this->turnOnlineOn($farmer);
        $order = $this->placeOnline($farmer);
        $buyer = User::query()->findOrFail($order->buyer_id);

        $this->asUser($buyer)->post("/api/buyer/orders/{$order->id}/payment-proofs", [
            'reference_number' => 'ab 12-cd',
            'amount' => 30,
            'qr_id' => $qr->id,
        ])->assertOk();

        return [$farmer, $order->fresh(), PaymentProof::query()->where('order_id', $order->id)->firstOrFail()];
    }

    private function placeOnline(User $farmer): Order
    {
        $listing = $this->listingFor($farmer, ['price_per_unit' => 30, 'quantity_available' => 10]);
        $buyer = User::query()->where('email', 'buyer-online@example.com')->first() ?? $this->buyer(['email' => 'buyer-online@example.com']);
        $this->addToCart($buyer, $listing, 1);

        $id = $this->checkout($buyer, [
            'payment_flow' => 'proof',
            'payments' => [
                ['seller_id' => $farmer->id, 'method' => PaymentMethod::OnlineTransfer->value],
            ],
        ])->assertCreated()->json('data.0.id');

        return Order::query()->findOrFail($id);
    }

    private function saveQr(User $farmer, string $last4 = '1234'): SellerPaymentQr
    {
        $id = $this->asUser($farmer)->post('/api/farmer/payment-qrs', $this->qrPayload($last4))
            ->assertCreated()
            ->json('data.id');

        return SellerPaymentQr::query()->findOrFail($id);
    }

    private function turnOnlineOn(User $farmer, int $hours = 24): void
    {
        $this->asUser($farmer)->patchJson('/api/farmer/shop', [
            'accepts_online_payment' => true,
            'payment_time_limit_hours' => $hours,
        ])->assertOk();
    }

    /**
     * @return array<string, mixed>
     */
    private function qrPayload(string $last4): array
    {
        return [
            'wallet' => 'gcash',
            'account_name' => 'Nena V',
            'account_last4' => $last4,
            'image' => UploadedFile::fake()->image('qr.jpg', 80, 80),
        ];
    }

    private function staff(Role $role, ?Farm $farm = null): User
    {
        $user = User::factory()->create(['farm_id' => $farm?->id]);
        $user->syncRoles($role);

        return $user;
    }

    private function notices(int $userId, NotificationType $type): int
    {
        return InAppNotification::query()
            ->where('user_id', $userId)
            ->where('type', $type)
            ->count();
    }
}
