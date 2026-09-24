<?php

namespace Database\Seeders;

use App\Actions\Orders\RecordWalkInSaleAction;
use App\Actions\Pricing\SetFarmPriceOverrideAction;
use App\Enums\AnnouncementAudience;
use App\Enums\ArticleCategory;
use App\Enums\ArticleStatus;
use App\Enums\CancellationReason;
use App\Enums\FulfillmentPreference;
use App\Enums\ListingStatus;
use App\Enums\ListingUnit;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Enums\TawadType;
use App\Enums\UserStatus;
use App\Models\CartItem;
use App\Models\CropCareArticle;
use App\Models\CropType;
use App\Models\FaqEntry;
use App\Models\Farm;
use App\Models\FarmAnnouncement;
use App\Models\FarmPhoto;
use App\Models\Listing;
use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\Review;
use App\Models\TawadRule;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Services\CheckoutService;
use App\Services\OrderStateMachine;
use App\Support\ListingStorage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

class DemoSeeder extends Seeder
{
    public const PASSWORD = 'password';

    public const EMAIL_DOMAIN = '@demo.anihow.local';

    public function run(): void
    {
        if (! $this->mayRun()) {
            $this->command?->warn('DemoSeeder skipped: not local/testing and not invoked with --class=DemoSeeder.');

            return;
        }

        $this->call(RolePermissionSeeder::class);

        $farm = $this->farm();
        $this->photos($farm);

        $crops = $this->cropTypes();
        app(SetFarmPriceOverrideAction::class)->execute($farm, $crops['kamatis'], 40.00, null);

        $editor = $this->contentEditor($farm);
        $sellers = $this->sellers($farm);
        $buyers = $this->buyers();
        $listings = $this->listings($sellers, $crops, $farm);
        $this->tawadRules($listings);

        if (! $this->demoHistoryExists()) {
            $this->seedHistory($buyers, $sellers, $listings);
        }

        $this->announcements($farm, $editor);
        $this->articles($farm, $editor, $crops);
        $this->faqOverride($farm);

        CartItem::query()
            ->whereIn('buyer_id', collect($buyers)->pluck('id'))
            ->delete();

        $this->report($farm, $sellers, $buyers, $editor);
    }

    private function mayRun(): bool
    {
        if (app()->environment(['local', 'testing'])) {
            return true;
        }

        $argv = $_SERVER['argv'] ?? [];

        return collect($argv)->contains(
            fn (mixed $arg): bool => is_string($arg) && str_contains($arg, 'DemoSeeder'),
        );
    }

    private function demoHistoryExists(): bool
    {
        return Order::query()
            ->whereHas('farmerSeller', fn ($query) => $query->where('email', 'like', '%'.self::EMAIL_DOMAIN))
            ->exists();
    }

    private function farm(): Farm
    {
        $cover = $this->storePlaceholder('farms/pyap-manggahan/cover.jpg', 'PYAP Manggahan', 46, 125, 50);

        return Farm::query()->updateOrCreate(
            ['slug' => 'pyap-manggahan-chapter'],
            [
                'name' => 'PYAP Manggahan Chapter',
                'description' => 'Youth and farmers of Barangay Manggahan, General Trias. Saturday harvest market at the PYAP hall: kamatis, talong, sitaw, and other backyard produce. Cash on handover only.',
                'contact_person' => 'Ka Elena Ramos',
                'contact_number' => '09175552100',
                'address' => 'PYAP Hall, Barangay Manggahan',
                'barangay' => 'Manggahan',
                'municipality' => 'General Trias',
                'pickup_point' => 'PYAP Hall, Manggahan — Saturdays 6:00 AM to 10:00 AM',
                'cover_photo_path' => $cover,
                'is_active' => true,
            ],
        );
    }

    private function photos(Farm $farm): void
    {
        $gallery = [
            [1, 'farms/pyap-manggahan/gallery-1.jpg', 'Morning harvest crates', 198, 40, 40],
            [2, 'farms/pyap-manggahan/gallery-2.jpg', 'Talong and sitaw from the plots', 106, 27, 154],
            [3, 'farms/pyap-manggahan/gallery-3.jpg', 'Saturday pickup at the hall', 245, 167, 26],
        ];

        foreach ($gallery as [$sort, $path, $caption, $r, $g, $b]) {
            FarmPhoto::query()->updateOrCreate(
                ['farm_id' => $farm->id, 'sort_order' => $sort],
                [
                    'path' => $this->storePlaceholder($path, $caption, $r, $g, $b),
                    'caption' => $caption,
                ],
            );
        }
    }

    /**
     * @return array<string, CropType>
     */
    private function cropTypes(): array
    {
        $rows = [
            'kamatis' => ['Kamatis', 'Tomato', 'Kamatis', ListingUnit::Kilogram, 35.00, 15.00, 'Ripe salad tomatoes from Cavite plots.'],
            'talong' => ['Talong', 'Eggplant', 'Talong', ListingUnit::Kilogram, 25.00, 12.00, 'Long purple eggplant, firm and glossy.'],
            'sitaw' => ['Sitaw', 'String beans', 'Sitaw', ListingUnit::Bundle, 15.00, 8.00, 'Yard-long beans, sold by the bundle.'],
            'kalabasa' => ['Kalabasa', 'Squash', 'Kalabasa', ListingUnit::Kilogram, 20.00, 10.00, 'Orange squash for ginataang gulay.'],
            'ampalaya' => ['Ampalaya', 'Bitter gourd', 'Ampalaya', ListingUnit::Kilogram, 30.00, 12.00, 'Bitter gourd, harvested young.'],
            'okra' => ['Okra', 'Okra', 'Okra', ListingUnit::Kilogram, 25.00, 10.00, 'Tender okra pods.'],
            'pechay' => ['Pechay', 'Pechay', 'Pechay', ListingUnit::Bundle, 12.00, 6.00, 'Leafy pechay, sold by the bundle.'],
            'mais' => ['Mais', 'Corn', 'Mais', ListingUnit::Piece, 8.00, 3.00, 'Sweet corn, sold by the ear.'],
            'sili' => ['Sili', 'Chili', 'Sili', ListingUnit::Kilogram, 80.00, 25.00, 'Hot siling labuyo and green chili.'],
        ];

        $crops = [];

        foreach ($rows as $key => [$name, $en, $fil, $unit, $floor, $max, $description]) {
            $crops[$key] = CropType::query()->updateOrCreate(
                ['slug' => 'demo-'.$key],
                [
                    'name' => $name,
                    'label_en' => $en,
                    'label_fil' => $fil,
                    'description' => $description,
                    'unit_of_measure' => $unit,
                    'floor_price' => $floor,
                    'max_discount' => $max,
                    'is_active' => true,
                ],
            );
        }

        return $crops;
    }

    private function contentEditor(Farm $farm): User
    {
        return $this->user(
            'elena.ramos'.self::EMAIL_DOMAIN,
            'Elena Ramos',
            Role::ContentEditor,
            $farm,
            [
                'phone' => '09175552100',
                'location' => 'Manggahan, General Trias, Cavite',
            ],
        );
    }

    /**
     * @return array<string, User>
     */
    private function sellers(Farm $farm): array
    {
        $rows = [
            'nena' => [
                'Nena Villanueva',
                'nena.villanueva'.self::EMAIL_DOMAIN,
                'Aling Nena Produce',
                'Backyard kamatis, talong, and sitaw. I harvest Friday night for Saturday pickup.',
                '09171234501',
            ],
            'tonyo' => [
                'Antonio Ramirez',
                'antonio.ramirez'.self::EMAIL_DOMAIN,
                'Mang Tonyo Farm',
                'Kalabasa, ampalaya, and mais from the Manggahan plots. Ask for the Saturday crate.',
                '09171234502',
            ],
            'rosa' => [
                'Rosa Mendoza',
                'rosa.mendoza'.self::EMAIL_DOMAIN,
                'Ka Rosa Gulay',
                'Leafy greens and sili. I pack pechay and sitaw the same morning.',
                '09171234503',
            ],
            'jun' => [
                'Jun Bautista',
                'jun.bautista'.self::EMAIL_DOMAIN,
                'Kuya Jun Harvest',
                'Talong, okra, and kalabasa. Walk-ins welcome at the hall after 6am.',
                '09171234504',
            ],
        ];

        $sellers = [];

        foreach ($rows as $key => [$name, $email, $shop, $bio, $phone]) {
            $sellers[$key] = $this->user($email, $name, Role::FarmerSeller, $farm, [
                'shop_name' => $shop,
                'bio' => $bio,
                'contact' => $phone,
                'phone' => $phone,
                'location' => 'Manggahan, General Trias, Cavite',
            ]);
        }

        return $sellers;
    }

    /**
     * @return array<int, User>
     */
    private function buyers(): array
    {
        $rows = [
            ['Carla Santos', 'carla.santos'.self::EMAIL_DOMAIN, '09190001101'],
            ['Miguel Reyes', 'miguel.reyes'.self::EMAIL_DOMAIN, '09190001102'],
            ['Ana Dela Cruz', 'ana.delacruz'.self::EMAIL_DOMAIN, '09190001103'],
            ['Paolo Garcia', 'paolo.garcia'.self::EMAIL_DOMAIN, '09190001104'],
            ['Liza Ramos', 'liza.ramos'.self::EMAIL_DOMAIN, '09190001105'],
            ['Benito Cruz', 'benito.cruz'.self::EMAIL_DOMAIN, '09190001106'],
        ];

        return collect($rows)
            ->map(fn (array $row): User => $this->user($row[1], $row[0], Role::Buyer, null, [
                'phone' => $row[2],
                'location' => 'General Trias, Cavite',
            ]))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function user(string $email, string $name, Role $role, ?Farm $farm, array $extra = []): User
    {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => self::PASSWORD,
                'status' => UserStatus::Active,
                'approved_at' => now(),
                'email_verified_at' => now(),
                'farm_id' => $farm?->id,
                ...$extra,
            ],
        );

        $user->syncRoles($role->value);

        return $user->fresh();
    }

    /**
     * @param  array<string, User>  $sellers
     * @param  array<string, CropType>  $crops
     * @return array<string, Listing>
     */
    private function listings(array $sellers, array $crops, Farm $farm): array
    {
        $historyExists = $this->demoHistoryExists();

        $rows = [
            'nena-kamatis' => ['nena', 'kamatis', 'Kamatis, bagong pitas', 'Ripe red tomatoes, harvested Friday. Good for salad and sawsawan.', 55.00, [198, 40, 40]],
            'nena-talong' => ['nena', 'talong', 'Talong, mahaba', 'Long purple eggplant from the backyard plot.', 40.00, [106, 27, 154]],
            'nena-sitaw' => ['nena', 'sitaw', 'Sitaw, sariwa', 'Yard-long beans, tied in bundles of about 250g.', 22.00, [56, 142, 60]],
            'nena-pechay' => ['nena', 'pechay', 'Pechay, bunot umaga', 'Leafy pechay, washed and bundled.', 18.00, [67, 160, 71]],
            'tonyo-kalabasa' => ['tonyo', 'kalabasa', 'Kalabasa, matamis', 'Orange squash, cut to order at the hall.', 35.00, [245, 124, 0]],
            'tonyo-ampalaya' => ['tonyo', 'ampalaya', 'Ampalaya, batang pitas', 'Young bitter gourd, less bitter if you salt it first.', 50.00, [85, 139, 47]],
            'tonyo-okra' => ['tonyo', 'okra', 'Okra, malambot', 'Tender okra, picked the same morning.', 45.00, [46, 125, 50]],
            'tonyo-mais' => ['tonyo', 'mais', 'Mais, matamis', 'Sweet corn, sold by the ear.', 12.00, [251, 192, 45]],
            'rosa-kamatis' => ['rosa', 'kamatis', 'Kamatis, salad size', 'Medium tomatoes, firm for packing.', 58.00, [183, 28, 28]],
            'rosa-sili' => ['rosa', 'sili', 'Sili, anghang', 'Green chili and a little labuyo mixed in.', 150.00, [198, 40, 40]],
            'rosa-sitaw' => ['rosa', 'sitaw', 'Sitaw, mahaba', 'Long sitaw, good for ginisang sitaw.', 24.00, [27, 94, 32]],
            'jun-talong' => ['jun', 'talong', 'Talong, pantatong', 'Firm eggplant for tortang talong.', 42.00, [81, 45, 168]],
            'jun-kalabasa' => ['jun', 'kalabasa', 'Kalabasa, pangkare-kare', 'Dense squash, sold by the kilo.', 38.00, [230, 81, 0]],
            'jun-pechay' => ['jun', 'pechay', 'Pechay, sariwa', 'Morning-cut pechay bundles.', 20.00, [56, 142, 60]],
        ];

        $listings = [];

        foreach ($rows as $key => [$sellerKey, $cropKey, $title, $description, $price, $rgb]) {
            $seller = $sellers[$sellerKey];
            $crop = $crops[$cropKey];
            $image = $this->storePlaceholder('listings/demo-'.$key.'.jpg', $title, $rgb[0], $rgb[1], $rgb[2]);

            $values = [
                'farm_id' => $farm->id,
                'title' => $title,
                'description' => $description,
                'price_per_unit' => $price,
                'is_active' => true,
                'status' => ListingStatus::Published,
                'image_path' => $image,
            ];

            if (! $historyExists) {
                $values['quantity_available'] = 200;
                $values['quantity_held'] = 0;
            }

            $listings[$key] = Listing::query()->updateOrCreate(
                [
                    'farmer_seller_id' => $seller->id,
                    'crop_type_id' => $crop->id,
                ],
                $values,
            )->fresh(['cropType', 'farmerSeller', 'farm.cropTypeOverrides', 'activeTawadRule']);
        }

        return $listings;
    }

    /**
     * @param  array<string, Listing>  $listings
     */
    private function tawadRules(array $listings): void
    {
        $rules = [
            'nena-kamatis' => [TawadType::MinimumQuantity, 15.00, 5.00],
            'tonyo-ampalaya' => [TawadType::Flat, 10.00, null],
            'rosa-sitaw' => [TawadType::MinimumQuantity, 8.00, 3.00],
            'jun-talong' => [TawadType::Flat, 5.00, null],
        ];

        foreach ($rules as $key => [$type, $amount, $minQty]) {
            $listing = $listings[$key];
            $listing->tawadRules()->update(['is_active' => false, 'ended_at' => now()]);

            TawadRule::query()->updateOrCreate(
                [
                    'listing_id' => $listing->id,
                    'type' => $type,
                    'discount_amount' => $amount,
                    'min_quantity' => $minQty,
                ],
                [
                    'is_active' => true,
                    'ended_at' => null,
                ],
            );
        }
    }

    /**
     * @param  array<int, User>  $buyers
     * @param  array<string, User>  $sellers
     * @param  array<string, Listing>  $listings
     */
    private function seedHistory(array $buyers, array $sellers, array $listings): void
    {
        $checkout = app(CheckoutService::class);
        $machine = app(OrderStateMachine::class);
        $walkIn = app(RecordWalkInSaleAction::class);

        $completedApp = [];

        try {
            $completedApp = [
                ...$this->completeAppOrders($checkout, $machine, $buyers, $listings),
                ...$this->cancelledOrders($checkout, $machine, $buyers, $listings),
                $this->liveOrder($checkout, $machine, $buyers[0], $listings['nena-pechay'], OrderStatus::Placed),
                $this->liveOrder($checkout, $machine, $buyers[1], $listings['tonyo-mais'], OrderStatus::Confirmed),
                $this->liveOrder($checkout, $machine, $buyers[2], $listings['rosa-sili'], OrderStatus::Ready),
            ];

            $this->walkInSales($walkIn, $sellers, $listings);
            $this->reviews(array_values(array_filter($completedApp)));
            $this->chats($completedApp);
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * @param  array<int, User>  $buyers
     * @param  array<string, Listing>  $listings
     * @return list<Order>
     */
    private function completeAppOrders(
        CheckoutService $checkout,
        OrderStateMachine $machine,
        array $buyers,
        array $listings,
    ): array {
        $plan = [
            [34, 0, 'nena-kamatis', 5.0, FulfillmentPreference::BuyerPickup],
            [33, 1, 'tonyo-kalabasa', 2.0, FulfillmentPreference::BuyerPickup],
            [32, 2, 'rosa-sitaw', 3.0, FulfillmentPreference::SellerDelivers],
            [31, 3, 'jun-talong', 2.0, FulfillmentPreference::BuyerPickup],
            [30, 4, 'nena-talong', 1.5, FulfillmentPreference::BuyerPickup],
            [29, 5, 'tonyo-ampalaya', 2.0, FulfillmentPreference::BuyerPickup],
            [28, 0, 'rosa-kamatis', 2.0, FulfillmentPreference::BuyerPickup],
            [27, 1, 'jun-kalabasa', 3.0, FulfillmentPreference::SellerDelivers],
            [26, 2, 'nena-sitaw', 4.0, FulfillmentPreference::BuyerPickup],
            [25, 3, 'tonyo-okra', 1.0, FulfillmentPreference::BuyerPickup],
            [23, 4, 'rosa-sili', 0.5, FulfillmentPreference::BuyerPickup],
            [21, 5, 'jun-pechay', 2.0, FulfillmentPreference::BuyerPickup],
            [19, 0, 'tonyo-mais', 6.0, FulfillmentPreference::BuyerPickup],
            [17, 1, 'nena-pechay', 3.0, FulfillmentPreference::SellerDelivers],
            [15, 2, 'jun-talong', 3.0, FulfillmentPreference::BuyerPickup],
            [13, 3, 'rosa-sitaw', 5.0, FulfillmentPreference::BuyerPickup],
            [11, 4, 'nena-kamatis', 2.0, FulfillmentPreference::BuyerPickup],
            [9, 5, 'tonyo-ampalaya', 1.5, FulfillmentPreference::BuyerPickup],
            [7, 0, 'jun-kalabasa', 2.0, FulfillmentPreference::BuyerPickup],
            [5, 1, 'rosa-kamatis', 3.0, FulfillmentPreference::SellerDelivers],
            [3, 2, 'nena-talong', 2.0, FulfillmentPreference::BuyerPickup],
            [2, 3, 'tonyo-kalabasa', 2.5, FulfillmentPreference::BuyerPickup],
            [1, 4, 'jun-pechay', 1.0, FulfillmentPreference::BuyerPickup],
        ];

        $orders = [];

        foreach ($plan as [$daysAgo, $buyerIndex, $listingKey, $qty, $preference]) {
            $orders[] = $this->placeAndAdvance(
                $checkout,
                $machine,
                $buyers[$buyerIndex],
                $listings[$listingKey],
                $qty,
                $preference,
                $daysAgo,
                [OrderStatus::Confirmed, OrderStatus::Ready, OrderStatus::Completed],
            );
        }

        return $orders;
    }

    /**
     * @param  array<int, User>  $buyers
     * @param  array<string, Listing>  $listings
     * @return list<Order>
     */
    private function cancelledOrders(
        CheckoutService $checkout,
        OrderStateMachine $machine,
        array $buyers,
        array $listings,
    ): array {
        $buyerCancel = $this->placeAndAdvance(
            $checkout,
            $machine,
            $buyers[5],
            $listings['nena-sitaw'],
            2.0,
            FulfillmentPreference::BuyerPickup,
            24,
            [],
        );
        $this->at($this->moment(24, 9), function () use ($machine, $buyerCancel, $buyers): void {
            $machine->transition(
                $buyerCancel->fresh(),
                OrderStatus::Cancelled,
                $buyers[5],
                'Changed plans, bibili next Saturday na lang.',
                CancellationReason::BuyerCancelled,
            );
        });

        $sellerDecline = $this->placeAndAdvance(
            $checkout,
            $machine,
            $buyers[0],
            $listings['tonyo-okra'],
            2.0,
            FulfillmentPreference::BuyerPickup,
            16,
            [],
        );
        $this->at($this->moment(16, 10), function () use ($machine, $sellerDecline, $listings): void {
            $machine->transition(
                $sellerDecline->fresh(),
                OrderStatus::Cancelled,
                $listings['tonyo-okra']->farmerSeller,
                'Ulan, hindi namin na-ani ang okra ngayong umaga.',
                CancellationReason::SellerDeclined,
            );
        });

        $noShow = $this->placeAndAdvance(
            $checkout,
            $machine,
            $buyers[1],
            $listings['rosa-kamatis'],
            2.0,
            FulfillmentPreference::BuyerPickup,
            8,
            [OrderStatus::Confirmed, OrderStatus::Ready],
        );
        $this->at($this->moment(8, 12), function () use ($machine, $noShow, $listings): void {
            $machine->transition(
                $noShow->fresh(),
                OrderStatus::Cancelled,
                $listings['rosa-kamatis']->farmerSeller,
                'Buyer did not arrive before the hall closed.',
                CancellationReason::NoShow,
            );
        });

        $buyerCancelTwo = $this->placeAndAdvance(
            $checkout,
            $machine,
            $buyers[2],
            $listings['jun-kalabasa'],
            1.0,
            FulfillmentPreference::SellerDelivers,
            6,
            [],
        );
        $this->at($this->moment(6, 9), function () use ($machine, $buyerCancelTwo, $buyers): void {
            $machine->transition(
                $buyerCancelTwo->fresh(),
                OrderStatus::Cancelled,
                $buyers[2],
                'May pasok pala, cancel muna.',
                CancellationReason::BuyerCancelled,
            );
        });

        return [$buyerCancel, $sellerDecline, $noShow, $buyerCancelTwo];
    }

    /**
     * @param  list<OrderStatus>  $steps
     */
    private function placeAndAdvance(
        CheckoutService $checkout,
        OrderStateMachine $machine,
        User $buyer,
        Listing $listing,
        float $quantity,
        FulfillmentPreference $preference,
        int $daysAgo,
        array $steps,
    ): Order {
        $order = $this->at($this->moment($daysAgo, 7), function () use ($checkout, $buyer, $listing, $quantity, $preference): Order {
            CartItem::query()->updateOrCreate(
                ['buyer_id' => $buyer->id, 'listing_id' => $listing->id],
                ['quantity' => $quantity],
            );

            return $checkout->checkout($buyer->fresh(), $preference, 'PYAP hall Saturday')->first();
        });

        $hours = 8;

        foreach ($steps as $status) {
            $this->at($this->moment($daysAgo, $hours), function () use ($machine, $order, $listing, $status): void {
                $fresh = $order->fresh();
                $amount = $status === OrderStatus::Completed ? (float) $fresh->total : null;
                $machine->transition($fresh, $status, $listing->farmerSeller, amountReceived: $amount);
            });
            $hours++;
        }

        return $order->fresh();
    }

    private function liveOrder(
        CheckoutService $checkout,
        OrderStateMachine $machine,
        User $buyer,
        Listing $listing,
        OrderStatus $stopAt,
    ): Order {
        $steps = match ($stopAt) {
            OrderStatus::Placed => [],
            OrderStatus::Confirmed => [OrderStatus::Confirmed],
            OrderStatus::Ready => [OrderStatus::Confirmed, OrderStatus::Ready],
            default => [],
        };

        return $this->placeAndAdvance(
            $checkout,
            $machine,
            $buyer,
            $listing,
            1.0,
            FulfillmentPreference::BuyerPickup,
            0,
            $steps,
        );
    }

    /**
     * @param  array<string, User>  $sellers
     * @param  array<string, Listing>  $listings
     */
    private function walkInSales(RecordWalkInSaleAction $walkIn, array $sellers, array $listings): void
    {
        $rows = [
            [20, 'nena', 'nena-pechay', 2.0, 36.00, 'Aling Beth'],
            [14, 'tonyo', 'tonyo-mais', 8.0, 96.00, 'Mang Cardo'],
            [10, 'rosa', 'rosa-sili', 0.3, 45.00, 'Ka Linda'],
            [4, 'jun', 'jun-talong', 1.5, 58.00, 'Walk-in from Navarro'],
        ];

        foreach ($rows as [$daysAgo, $sellerKey, $listingKey, $qty, $received, $name]) {
            $this->at($this->moment($daysAgo, 11), function () use ($walkIn, $sellers, $listings, $sellerKey, $listingKey, $qty, $received, $name): void {
                $walkIn->execute(
                    $sellers[$sellerKey]->fresh(),
                    $listings[$listingKey]->fresh(),
                    $qty,
                    $received,
                    $name,
                    'Saturday hall walk-in.',
                );
            });
        }
    }

    /**
     * @param  list<Order>  $orders
     */
    private function reviews(array $orders): void
    {
        $comments = [
            'Kamatis was ripe. Will order again next Saturday.',
            'Mabait si Aling Nena, sariwa ang gulay.',
            'Ampalaya was young, less bitter than the palengke.',
            'On time at the hall. Cash handover was easy.',
            'Sitaw was long and crisp.',
            'Talong good for torta. Salamat.',
            'Pechay still perked up after a soak.',
            'Mais was sweet. Kids finished two ears.',
            'Sili had a real kick. Pack more next time.',
            'Kalabasa dense, good for ginataan.',
            'Seller packed it well. No bruises.',
            'Bumalik ako. Fair price, no usapan sa chat.',
        ];

        $completed = collect($orders)
            ->filter(fn (Order $order): bool => $order->status === OrderStatus::Completed && $order->buyer_id !== null)
            ->values();

        foreach ($completed->take((int) ceil($completed->count() / 2)) as $index => $order) {
            Review::query()->firstOrCreate(
                ['order_id' => $order->id],
                [
                    'buyer_id' => $order->buyer_id,
                    'farmer_seller_id' => $order->farmer_seller_id,
                    'rating' => 4 + ($index % 2),
                    'comment' => $comments[$index % count($comments)],
                ],
            );
        }
    }

    /**
     * @param  list<Order>  $orders
     */
    private function chats(array $orders): void
    {
        $ready = collect($orders)->first(
            fn (Order $order): bool => $order->status === OrderStatus::Ready,
        );

        if ($ready instanceof Order) {
            OrderMessage::query()->firstOrCreate(
                ['order_id' => $ready->id, 'user_id' => $ready->buyer_id, 'body' => 'Po, aalis ako ng 8am. Kita tayo sa hall.'],
            );
            OrderMessage::query()->firstOrCreate(
                ['order_id' => $ready->id, 'user_id' => $ready->farmer_seller_id, 'body' => 'Sige, naka-pack na. Hanapin mo ang crate na may sticker ni Ka Rosa.'],
            );
        }

        $firstCompleted = collect($orders)->first(
            fn (Order $order): bool => $order->status === OrderStatus::Completed && $order->buyer_id !== null,
        );

        if ($firstCompleted instanceof Order) {
            OrderMessage::query()->firstOrCreate(
                ['order_id' => $firstCompleted->id, 'user_id' => $firstCompleted->buyer_id, 'body' => 'Salamat! Sariwa talaga.'],
            );
        }
    }

    private function announcements(Farm $farm, User $editor): void
    {
        FarmAnnouncement::query()->updateOrCreate(
            ['farm_id' => $farm->id, 'title' => 'Saturday harvest is on — come early'],
            [
                'author_id' => $editor->id,
                'body' => 'Pickup at the PYAP hall, 6:00 AM to 10:00 AM. Bring cash. Walk-ins welcome after reserved orders are packed.',
                'audience' => AnnouncementAudience::Public,
                'starts_at' => now()->subDays(2)->startOfDay(),
                'ends_at' => now()->addDays(5)->endOfDay(),
                'is_pinned' => true,
            ],
        );

        FarmAnnouncement::query()->updateOrCreate(
            ['farm_id' => $farm->id, 'title' => 'Members: extra pechay crates tomorrow'],
            [
                'author_id' => $editor->id,
                'body' => 'If you already ordered pechay, we have two extra crates from Ka Rosa. Message your seller before 7am.',
                'audience' => AnnouncementAudience::Members,
                'starts_at' => now()->addDay()->startOfDay(),
                'ends_at' => now()->addDays(2)->endOfDay(),
                'is_pinned' => false,
            ],
        );
    }

    /**
     * @param  array<string, CropType>  $crops
     */
    private function articles(Farm $farm, User $editor, array $crops): void
    {
        $rows = [
            [
                'slug' => 'kamatis-in-cavite-heat',
                'title' => 'Keeping kamatis productive in Cavite heat',
                'excerpt' => 'Mulch, water at the base, and pick often so plants keep setting.',
                'body' => "Mulch the beds to hold moisture through General Trias dry spells.\n\nWater at the base early in the morning. Pick ripe fruit often so plants keep setting. Watch for leaf spots after heavy rain and remove affected leaves. This is general crop-care guidance, not a spray calendar.",
                'category' => ArticleCategory::CropCare,
                'tags' => ['kamatis'],
            ],
            [
                'slug' => 'ampalaya-fruit-fly-watch',
                'title' => 'Watching fruit fly on ampalaya',
                'excerpt' => 'Bag young fruit and check the underside of leaves weekly.',
                'body' => "Fruit fly marks show as soft spots on young ampalaya.\n\nBag fruit early, pick damaged fruit off the plot, and do not leave culls on the ground. Check the underside of leaves weekly. AniHow does not schedule sprays; this note is a field reminder only.",
                'category' => ArticleCategory::PestManagement,
                'tags' => ['ampalaya'],
            ],
            [
                'slug' => 'sitaw-and-pechay-after-rain',
                'title' => 'Sitaw and pechay after a Cavite downpour',
                'excerpt' => 'Stake sitaw, drain pechay beds, and harvest wet leaves the same morning.',
                'body' => "After heavy rain, sitaw vines sag and pechay beds hold water.\n\nRe-stake sitaw, open a drain at the foot of the pechay row, and harvest wet leaves the same morning so they do not rot in the crate. Guidance only — not a weather forecast.",
                'category' => ArticleCategory::CropCare,
                'tags' => ['sitaw', 'pechay'],
            ],
        ];

        foreach ($rows as $row) {
            $article = CropCareArticle::query()->updateOrCreate(
                ['farm_id' => $farm->id, 'slug' => $row['slug']],
                [
                    'created_by' => $editor->id,
                    'title' => $row['title'],
                    'excerpt' => $row['excerpt'],
                    'body' => $row['body'],
                    'category' => $row['category'],
                    'status' => ArticleStatus::Published,
                    'published_at' => now()->subDays(3),
                ],
            );

            $article->cropTypes()->sync(
                collect($row['tags'])->map(fn (string $key): int => $crops[$key]->id)->all(),
            );
        }
    }

    private function faqOverride(Farm $farm): void
    {
        FaqEntry::query()->updateOrCreate(
            [
                'farm_id' => $farm->id,
                'intent_key' => 'pickup',
            ],
            [
                'roles' => ['buyer'],
                'label' => 'How does pickup work at PYAP Manggahan?',
                'label_fil' => 'Paano ang pickup sa PYAP Manggahan?',
                'keywords' => ['pickup', 'pick up', 'hall', 'pyap', 'manggahan', 'saturday', 'salo'],
                'answer' => 'Meet at the PYAP Hall in Barangay Manggahan, General Trias. Saturday pickup is 6:00 AM to 10:00 AM. Bring cash. When your order says Ready, look for your seller\'s crate. Walk-ins are packed after reserved orders.',
                'answer_fil' => 'Magkita kayo sa PYAP Hall, Barangay Manggahan, General Trias. Sabado ang pickup, 6:00 AM hanggang 10:00 AM. Magdala ng cash. Kapag Ready na ang order, hanapin ang crate ng seller mo. Ang walk-in ay sinusunod pagkatapos ng reserved orders.',
                'sort_order' => 0,
                'is_active' => true,
            ],
        );
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function at(Carbon $when, callable $callback): mixed
    {
        Carbon::setTestNow($when);

        try {
            return $callback();
        } finally {
            Carbon::setTestNow();
        }
    }

    private function moment(int $daysAgo, int $hour): Carbon
    {
        return now()->subDays($daysAgo)->setTime($hour, 15, 0);
    }

    private function storePlaceholder(string $path, string $label, int $red, int $green, int $blue): string
    {
        $disk = ListingStorage::disk();

        if ($disk->exists($path)) {
            return $path;
        }

        $disk->put($path, $this->jpegBytes($label, $red, $green, $blue));

        return $path;
    }

    private function jpegBytes(string $label, int $red, int $green, int $blue): string
    {
        if (function_exists('imagecreatetruecolor')) {
            $image = imagecreatetruecolor(800, 500);
            $background = imagecolorallocate($image, $red, $green, $blue);
            $ink = imagecolorallocate($image, 255, 255, 255);
            imagefilledrectangle($image, 0, 0, 800, 500, $background);
            imagestring($image, 5, 24, 230, $label, $ink);
            ob_start();
            imagejpeg($image, null, 82);
            $bytes = (string) ob_get_clean();
            imagedestroy($image);

            return $bytes;
        }

        $fallback = public_path('images/produce/tomato.jpg');

        if (is_file($fallback)) {
            return (string) File::get($fallback);
        }

        return (string) base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wAAADs=', true);
    }

    /**
     * @param  array<string, User>  $sellers
     * @param  array<int, User>  $buyers
     */
    private function report(Farm $farm, array $sellers, array $buyers, User $editor): void
    {
        $analytics = app(AnalyticsService::class);
        $summary = $analytics->completedSummary(null, 35);

        $this->command?->newLine();
        $this->command?->info('Demo data ready for PYAP Manggahan Chapter.');
        $this->command?->line('Shared password for every @demo.anihow.local account: '.self::PASSWORD);
        $this->command?->newLine();
        $this->command?->table(
            ['Role', 'Name', 'Email', 'Notes'],
            [
                ['content_editor', $editor->name, $editor->email, 'Filament /admin — farm: '.$farm->name],
                ...collect($sellers)->map(fn (User $seller): array => [
                    'farmer_seller',
                    $seller->name,
                    $seller->email,
                    'Shop: '.$seller->shop_name,
                ])->all(),
                ...collect($buyers)->map(fn (User $buyer): array => [
                    'buyer',
                    $buyer->name,
                    $buyer->email,
                    'Verified',
                ])->all(),
            ],
        );
        $this->command?->line('Completed orders (35d): '.$summary['completed_orders']
            .'  Gross sales: PHP '.number_format($summary['gross_sales'], 2)
            .'  Units: '.$summary['units_sold']);
        $this->command?->warn('Never run DemoSeeder on the production server.');
    }
}
