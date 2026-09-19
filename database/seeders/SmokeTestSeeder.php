<?php

namespace Database\Seeders;

use App\Enums\ArticleCategory;
use App\Enums\ArticleStatus;
use App\Enums\FulfillmentPreference;
use App\Enums\ListingStatus;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Enums\TawadType;
use App\Enums\UserStatus;
use App\Models\CartItem;
use App\Models\CropCareArticle;
use App\Models\CropType;
use App\Models\Farm;
use App\Models\Listing;
use App\Models\Review;
use App\Models\TawadRule;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\OrderStateMachine;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Walks one order from cart to completed handover, checking the things that
 * are easy to get wrong and impossible to see from route:list.
 *
 * Run:  php artisan db:seed --class=SmokeTestSeeder
 *
 * Safe to re-run. It reuses its own records by email and slug.
 */
class SmokeTestSeeder extends Seeder
{
    private int $passed = 0;

    private int $failed = 0;

    public function run(): void
    {
        $farm = $this->farm();
        $cropType = $this->cropType();
        $editor = $this->contentEditor($farm);
        $sellerA = $this->seller($farm, 'A', 'Aling Nena Produce');
        $sellerB = $this->seller($farm, 'B', 'Mang Tonyo Farm');
        $buyer = $this->buyer();

        $listingA = $this->listing($sellerA, $cropType, 'Fresh kamatis, hand picked', 30.00, 100);
        $listingB = $this->listing($sellerB, $cropType, 'Kamatis, bagong ani', 28.00, 50);

        $rule = $this->tawadRule($listingA);
        $this->article($farm, $editor, $cropType);

        $this->check(
            'Tawad rule keeps the unit price above the floor',
            $rule->keepsUnitPriceAboveFloor($listingA, $cropType),
        );

        // ---- cart spanning two sellers -------------------------------------
        CartItem::query()->where('buyer_id', $buyer->id)->delete();
        $buyer->cartItems()->create(['listing_id' => $listingA->id, 'quantity' => 6]);
        $buyer->cartItems()->create(['listing_id' => $listingB->id, 'quantity' => 2]);

        $orders = app(CheckoutService::class)->checkout(
            $buyer,
            FulfillmentPreference::BuyerPickup,
            'Saturday 7am at the barangay hall.',
        );

        $this->check('Cart split into one order per seller', $orders->count() === 2);
        $this->check('Cart emptied after checkout', $buyer->cartItems()->count() === 0);

        $orderA = $orders->firstWhere('farmer_seller_id', $sellerA->id);
        $orderB = $orders->firstWhere('farmer_seller_id', $sellerB->id);

        // ---- tawad ----------------------------------------------------------
        // 6 kg at PHP 30 is 180. The rule takes PHP 20 off at 5 kg and above.
        $this->check('Subtotal is the listed price times quantity', (float) $orderA->subtotal === 180.00);
        $this->check('Tawad applied at the minimum quantity', (float) $orderA->tawad_total === 20.00);
        $this->check('Total is subtotal minus tawad', (float) $orderA->total === 160.00);

        $itemA = $orderA->items->first();
        $this->check('Listed price is not overwritten by the tawad', (float) $itemA->unit_price === 30.00);

        // Seller B has no rule, so no discount.
        $this->check('Order without a rule carries no tawad', (float) $orderB->tawad_total === 0.00);

        // ---- stock held, not deducted ---------------------------------------
        $listingA->refresh();
        $this->check('Stock held at Placed', (float) $listingA->quantity_held === 6.00);
        $this->check('Stock not yet deducted at Placed', (float) $listingA->quantity_available === 100.00);
        $this->check('Sellable quantity excludes held stock', $listingA->sellableQuantity() === 94.00);

        // ---- confirm --------------------------------------------------------
        $machine = app(OrderStateMachine::class);
        $orderA = $machine->transition($orderA, OrderStatus::Confirmed, $sellerA);

        $listingA->refresh();
        $this->check('Stock deducted at Confirmed', (float) $listingA->quantity_available === 94.00);
        $this->check('Hold released at Confirmed', (float) $listingA->quantity_held === 0.00);
        $this->check('confirmed_at stamped', $orderA->confirmed_at !== null);

        // ---- a buyer cannot cancel after Confirmed --------------------------
        $this->check(
            'Buyer cannot cancel a confirmed order',
            $this->throws(fn () => $machine->transition($orderA, OrderStatus::Cancelled, $buyer)),
        );

        // ---- a status cannot skip ahead -------------------------------------
        $this->check(
            'Confirmed cannot jump to Completed',
            $this->throws(fn () => $machine->transition($orderA, OrderStatus::Completed, $sellerA)),
        );

        // ---- ready, then completed -----------------------------------------
        $orderA = $machine->transition($orderA, OrderStatus::Ready, $sellerA);
        $this->check('Ready reached', $orderA->status === OrderStatus::Ready);

        $this->check(
            'Completing without the cash figure is refused',
            $this->throws(fn () => $machine->transition($orderA, OrderStatus::Completed, $sellerA)),
        );

        $orderA = $machine->transition(
            order: $orderA,
            next: OrderStatus::Completed,
            actor: $sellerA,
            amountReceived: 160.00,
        );

        $this->check('Completed reached', $orderA->status === OrderStatus::Completed);
        $this->check('Cash received recorded', (float) $orderA->amount_received === 160.00);
        $this->check('Status history written', $orderA->statusHistories()->count() >= 3);

        // ---- review unlocks at Completed ------------------------------------
        $this->check('Review unlocks at Completed', $orderA->fresh()->canBeReviewed());

        Review::query()->where('order_id', $orderA->id)->delete();
        Review::create([
            'order_id' => $orderA->id,
            'buyer_id' => $buyer->id,
            'farmer_seller_id' => $sellerA->id,
            'rating' => 5,
            'comment' => 'Sariwa ang kamatis, salamat po.',
        ]);

        $this->check('One review per order', ! $orderA->fresh()->canBeReviewed());

        // ---- cancelling an unconfirmed order releases the hold --------------
        $listingB->refresh();
        $heldBefore = (float) $listingB->quantity_held;
        $availableBefore = (float) $listingB->quantity_available;

        $machine->transition(
            order: $orderB,
            next: OrderStatus::Cancelled,
            actor: $sellerB,
            reason: \App\Enums\CancellationReason::NoShow,
            note: 'Buyer did not arrive.',
        );

        $listingB->refresh();
        $this->check('Cancel before Confirmed releases the hold', (float) $listingB->quantity_held === $heldBefore - 2.00);
        $this->check('Cancel before Confirmed deducts nothing', (float) $listingB->quantity_available === $availableBefore);
        $this->check('No-show recorded as a cancellation reason', $orderB->fresh()->cancellation_reason?->value === 'no_show');

        $this->report($orderA);
    }

    // ---------------------------------------------------------------- fixtures

    private function farm(): Farm
    {
        return Farm::updateOrCreate(
            ['slug' => 'smoke-test-farm'],
            [
                'name' => 'Smoke Test Farm',
                'municipality' => 'General Trias',
                'barangay' => 'Manggahan',
                'pickup_point' => 'Barangay hall, Saturdays 7am to 10am',
                'is_active' => true,
            ],
        );
    }

    private function cropType(): CropType
    {
        // floor 25, max tawad 20. The database check requires the ceiling to
        // sit below the floor.
        return CropType::updateOrCreate(
            ['slug' => 'smoke-kamatis'],
            [
                'name' => 'Kamatis (smoke test)',
                'label_en' => 'Tomato',
                'label_fil' => 'Kamatis',
                'unit_of_measure' => 'kg',
                'floor_price' => 25.00,
                'max_discount' => 20.00,
                'is_active' => true,
            ],
        );
    }

    private function contentEditor(Farm $farm): User
    {
        return $this->user('smoke.editor@anihow.local', 'Smoke Content Editor', Role::ContentEditor, $farm);
    }

    private function seller(Farm $farm, string $suffix, string $shopName): User
    {
        $user = $this->user(
            'smoke.seller'.strtolower($suffix).'@anihow.local',
            'Smoke Seller '.$suffix,
            Role::FarmerSeller,
            $farm,
        );

        $user->update(['shop_name' => $shopName, 'location' => 'Manggahan, General Trias']);

        return $user;
    }

    private function buyer(): User
    {
        return $this->user('smoke.buyer@anihow.local', 'Smoke Buyer', Role::Buyer, null);
    }

    private function user(string $email, string $name, Role $role, ?Farm $farm): User
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'status' => UserStatus::Active,
                'approved_at' => now(),
                'email_verified_at' => now(),
                'farm_id' => $farm?->id,
            ],
        );

        // One account, one role.
        $user->syncRoles($role->value);

        return $user;
    }

    private function listing(User $seller, CropType $cropType, string $title, float $price, float $qty): Listing
    {
        $listing = Listing::updateOrCreate(
            ['farmer_seller_id' => $seller->id, 'crop_type_id' => $cropType->id],
            [
                'farm_id' => $seller->farm_id,
                'title' => $title,
                'description' => 'Created by SmokeTestSeeder.',
                'price_per_unit' => $price,
                'quantity_available' => $qty,
                'quantity_held' => 0,
                'is_active' => true,
                'status' => ListingStatus::Published,
            ],
        );

        // Re-running must start from a clean stock position.
        $listing->update(['quantity_available' => $qty, 'quantity_held' => 0]);

        return $listing->refresh();
    }

    private function tawadRule(Listing $listing): TawadRule
    {
        $listing->tawadRules()->update(['is_active' => false, 'ended_at' => now()]);

        // PHP 20 off at 5 kg and above.
        return $listing->tawadRules()->create([
            'type' => TawadType::MinimumQuantity,
            'discount_amount' => 20.00,
            'min_quantity' => 5.00,
            'is_active' => true,
        ]);
    }

    private function article(Farm $farm, User $editor, CropType $cropType): CropCareArticle
    {
        $article = CropCareArticle::updateOrCreate(
            ['farm_id' => $farm->id, 'slug' => 'kamatis-pest-watch'],
            [
                'created_by' => $editor->id,
                'title' => 'Watching for pests on kamatis',
                'body' => "Check the underside of leaves weekly.\n\nCreated by SmokeTestSeeder.",
                'category' => ArticleCategory::PestManagement,
                'status' => ArticleStatus::Published,
                'published_at' => now(),
            ],
        );

        $article->cropTypes()->sync([$cropType->id]);

        return $article;
    }

    // ------------------------------------------------------------------ output

    private function throws(callable $callback): bool
    {
        try {
            $callback();

            return false;
        } catch (\Throwable) {
            return true;
        }
    }

    private function check(string $label, bool $passed): void
    {
        if ($passed) {
            $this->passed++;
            $this->command->info('  PASS  '.$label);

            return;
        }

        $this->failed++;
        $this->command->error('  FAIL  '.$label);
    }

    private function report($order): void
    {
        $analytics = app(\App\Services\AnalyticsService::class);
        $discount = $analytics->averageDiscount(null, 30);
        $units = $analytics->unitsSoldPerCropType(null, 30);

        $this->command->line('');
        $this->command->line("  {$this->passed} passed, {$this->failed} failed");
        $this->command->line('');
        $this->command->line('  Order '.$order->order_number.' completed.');
        $this->command->line('  Completed orders: '.$discount['orders']);
        $this->command->line('  Average tawad: PHP '.number_format($discount['average'], 2));

        foreach ($units as $row) {
            $this->command->line('  '.$row->crop.': '.number_format((float) $row->units, 2).' '.$row->unit_of_measure);
        }

        $this->command->line('');
        $this->command->line('  Reload /admin. The dashboard should no longer be zero.');
    }
}
