<?php

namespace App\Actions\Testing;

use App\Actions\Listings\AddStockAction;
use App\Actions\Listings\CreateListingAction;
use App\Actions\Listings\RemoveStockAction;
use App\Actions\Listings\UpdateListingAction;
use App\Actions\Orders\RecordWalkInSaleAction;
use App\Actions\Payments\RefundOrderAction;
use App\Actions\Payments\ReviewPaymentProofAction;
use App\Actions\Payments\ReviewReservationPaymentProofAction;
use App\Actions\Payments\SubmitPaymentProofAction;
use App\Actions\Payments\SubmitReservationPaymentProofAction;
use App\Actions\Pricing\SetFarmPriceOverrideAction;
use App\Actions\Reservations\OpenDueReservations;
use App\Actions\Reservations\ReserveListing;
use App\Enums\AnnouncementAudience;
use App\Enums\ArticleCategory;
use App\Enums\ArticleStatus;
use App\Enums\CancellationReason;
use App\Enums\FulfillmentPreference;
use App\Enums\HarvestRejectionReason;
use App\Enums\ListingUnit;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentRejectionReason;
use App\Enums\PaymentWallet;
use App\Enums\Role;
use App\Enums\StockRemovalReason;
use App\Enums\TawadType;
use App\Enums\UserStatus;
use App\Models\CartItem;
use App\Models\CropCareArticle;
use App\Models\CropType;
use App\Models\FaqEntry;
use App\Models\Farm;
use App\Models\FarmAnnouncement;
use App\Models\HarvestRecord;
use App\Models\Listing;
use App\Models\Order;
use App\Models\PaymentProof;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\SellerPaymentQr;
use App\Models\StallConversation;
use App\Models\StallMessage;
use App\Models\StockRemoval;
use App\Models\TawadRule;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\OrderStateMachine;
use App\Support\Demo\ClientFarms;
use App\Support\ImageVariants;
use App\Support\ListingStorage;
use App\Support\Pricing\PriceGuardResolver;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CreateDemoData
{
    public const string PASSWORD = 'password';

    public const string EMAIL_DOMAIN = '@demo.anihow.local';

    private const int SEED = 20261010;

    /** The old demo seeder wrote these onto PYAP; a real call button must not dial them. */
    private const string OLD_DEMO_CONTACT_PERSON = 'Ka Elena Ramos';

    private const string OLD_DEMO_CONTACT_NUMBER = '09175552100';

    /** @var array<string, string> */
    private const array CROP_PHOTOS = [
        'kamatis' => 'tomato.jpg',
        'talong' => 'eggplant.jpg',
        'sitaw' => 'sitaw.jpg',
        'kalabasa' => 'squash.jpg',
        'ampalaya' => 'ampalaya.jpg',
        'okra' => 'okra.jpg',
        'pechay' => 'pechay.jpg',
        'mais' => 'corn.jpg',
        'sili' => 'chili.jpg',
    ];

    private Carbon $end;

    private int $references = 0;

    public function __construct(
        private CheckoutService $checkout,
        private OrderStateMachine $orders,
        private CreateListingAction $createListing,
        private AddStockAction $addStock,
        private RemoveStockAction $removeStock,
        private RecordWalkInSaleAction $walkIns,
        private UpdateListingAction $updateListing,
        private ReserveListing $reserveListing,
        private OpenDueReservations $openDue,
        private SubmitPaymentProofAction $submitProof,
        private ReviewPaymentProofAction $reviewProof,
        private SubmitReservationPaymentProofAction $submitReservationProof,
        private ReviewReservationPaymentProofAction $reviewReservationProof,
        private RefundOrderAction $refunds,
        private PriceGuardResolver $priceGuards,
        private SetFarmPriceOverrideAction $priceOverrides,
    ) {}

    /**
     * @return list<array{0: string, 1: string}>
     */
    public function handle(int $weeks = 26): array
    {
        $weeks = max(1, $weeks);
        $previous = [
            'mail' => config('mail.default'),
            'broadcast' => config('broadcasting.default'),
            'queue' => config('queue.default'),
        ];

        config([
            'mail.default' => 'log',
            'broadcasting.default' => 'null',
            'queue.default' => 'sync',
        ]);

        try {
            return DB::transaction(function () use ($weeks): array {
                (new RolePermissionSeeder)->run();
                mt_srand(self::SEED);
                $this->end = now()->copy();
                $this->write($weeks);

                return $this->summary();
            });
        } finally {
            Carbon::setTestNow();
            config([
                'mail.default' => $previous['mail'],
                'broadcasting.default' => $previous['broadcast'],
                'queue.default' => $previous['queue'],
            ]);
        }
    }

    private function write(int $weeks): void
    {
        $start = $this->end->copy()->subWeeks($weeks)->startOfDay();
        $farms = $this->farms();
        $this->retireDemoEditor($farms['pyap']);

        $editors = $this->editors($farms);
        $sellers = $this->sellers($farms);
        $buyers = $this->buyers();
        $crops = $this->crops();
        $this->pyapKamatisFloor($farms['pyap'], $crops['kamatis']);

        Carbon::setTestNow($start);
        $listings = $this->listings($sellers, $crops);
        Carbon::setTestNow();

        $this->tawadRules($listings);

        if (! $this->historyExists()) {
            $this->history($weeks, $start, $buyers, $listings);
            $this->reviews();
            $this->chats();
            Carbon::setTestNow();
            $this->currentState($farms, $sellers, $buyers, $listings);
        }

        Carbon::setTestNow();
        $this->announcements($farms, $editors);
        $this->articles($farms, $editors, $crops);
        $this->faq($farms['pyap']);
    }

    /**
     * @return array<string, Farm>
     */
    private function farms(): array
    {
        $extras = [
            ClientFarms::PYAP_SLUG => [
                'description' => 'Youth and farmers of Barangay Manggahan, General Trias. Saturday harvest market at the PYAP hall.',
                'address' => 'PYAP Hall, Barangay Manggahan',
                'pickup_point' => 'PYAP Hall, Manggahan — Saturdays 6:00 AM to 10:00 AM',
            ],
        ];

        $farms = [];

        foreach (ClientFarms::definitions() as $definition) {
            $farm = $this->findFarm($definition);

            if ($farm === null) {
                $farm = Farm::query()->create([
                    'name' => $definition['name'],
                    'slug' => $definition['slug'],
                    'municipality' => $definition['municipality'],
                    'barangay' => $definition['barangay'],
                    'description' => $definition['description'],
                    'is_active' => true,
                ]);
            }

            if ($definition['slug'] === ClientFarms::PYAP_SLUG
                && $farm->contact_person === self::OLD_DEMO_CONTACT_PERSON
                && $farm->contact_number === self::OLD_DEMO_CONTACT_NUMBER) {
                $farm->contact_person = null;
                $farm->contact_number = null;
            }

            foreach ($extras[$definition['slug']] ?? [] as $field => $value) {
                $this->fillBlank($farm, $field, $value);
            }

            foreach (['description', 'municipality', 'barangay'] as $field) {
                $this->fillBlank($farm, $field, $definition[$field]);
            }

            if (blank($farm->cover_photo_path)) {
                $farm->cover_photo_path = $this->storePublicPhoto(
                    'farms/'.$definition['slug'].'/cover.jpg',
                    'farm-cover.jpg',
                );
            }

            $farm->save();
            $farms[$definition['slug']] = $farm->refresh();
        }

        return [
            'pyap' => $farms[ClientFarms::PYAP_SLUG],
            'truofa' => $farms[ClientFarms::TRUOFA_SLUG],
            'sanctuario' => $farms[ClientFarms::SANCTUARIO_SLUG],
        ];
    }

    /**
     * @param  array{slug: string, name: string, also_names: list<string>}  $definition
     */
    private function findFarm(array $definition): ?Farm
    {
        $farm = Farm::query()->where('slug', $definition['slug'])->first();

        if ($farm !== null) {
            return $farm;
        }

        $names = array_merge([$definition['name']], $definition['also_names']);

        return Farm::query()
            ->whereIn(DB::raw('LOWER(name)'), array_map(
                fn (string $name): string => mb_strtolower($name),
                $names,
            ))
            ->first();
    }

    private function fillBlank(Farm $farm, string $field, ?string $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $current = $farm->getAttribute($field);

        if ($current === null || $current === '') {
            $farm->setAttribute($field, $value);
        }
    }

    private function retireDemoEditor(Farm $pyap): void
    {
        $elena = User::query()->where('email', 'elena.ramos'.self::EMAIL_DOMAIN)->first();

        if ($elena === null || $elena->hasRole(Role::SuperAdmin->value)) {
            return;
        }

        $elena->removeRole(Role::ContentEditor->value);

        if ((int) $elena->farm_id === (int) $pyap->id) {
            $elena->forceFill(['farm_id' => null])->save();
        }
    }

    /**
     * @param  array<string, Farm>  $farms
     * @return array<string, User>
     */
    private function editors(array $farms): array
    {
        return [
            'pyap' => $this->editor($farms['pyap'], 'editor01@gmail.com', 'Editor 01'),
            'sanctuario' => $this->editor($farms['sanctuario'], 'editor.sanctuario'.self::EMAIL_DOMAIN, 'Sanctuario Demo Editor'),
        ];
    }

    /**
     * A farm has one Content Editor. When someone else already holds the
     * farm, keep them and let them author the demo posts.
     */
    private function editor(Farm $farm, string $email, string $name): User
    {
        $current = $farm->contentEditor()->first();

        if ($current !== null && $current->email !== $email) {
            return $current;
        }

        return $this->account($email, $name, Role::ContentEditor, $farm);
    }

    /**
     * @param  array<string, Farm>  $farms
     * @return array<string, User>
     */
    private function sellers(array $farms): array
    {
        $rows = [
            'nena' => ['Nena Villanueva', 'nena.villanueva', "Nena's Backyard Gulay", 'pyap', true],
            'tonyo' => ['Antonio Ramirez', 'antonio.ramirez', 'Ramirez Plot Harvest', 'pyap', false],
            'rosa' => ['Rosa Mendoza', 'rosa.mendoza', 'Ka Rosa Gulay', 'pyap', true],
            'jun' => ['Jun Bautista', 'jun.bautista', 'Bautista Morning Pick', 'pyap', false],
            'liza' => ['Liza Cruz', 'liza.cruz', 'Cruz Morning Greens', 'truofa', true],
            'pedro' => ['Pedro Santos', 'pedro.santos', 'Santos Hillside Plots', 'truofa', false],
            'mira' => ['Mira Dela Cruz', 'mira.delacruz', 'Dela Cruz Garden Rows', 'truofa', true],
            'carla' => ['Carla Navarro', 'carla.navarro', 'Navarro Quiet Rows', 'sanctuario', true],
            'oscar' => ['Oscar Villamor', 'oscar.villamor', 'Villamor Creek Baskets', 'sanctuario', false],
        ];

        $sellers = [];

        foreach ($rows as $key => [$name, $local, $shop, $farmKey, $online]) {
            $seller = $this->account(
                $local.self::EMAIL_DOMAIN,
                $name,
                Role::FarmerSeller,
                $farms[$farmKey],
                [
                    'shop_name' => $shop,
                    'bio' => $shop.' — demo stall for '.$farms[$farmKey]->name.'.',
                    'location' => 'Cavite',
                ],
            );

            if ($online) {
                $this->paymentQr($seller);
            }

            $sellers[$key] = $seller;
        }

        return $sellers;
    }

    /**
     * @return list<User>
     */
    private function buyers(): array
    {
        $rows = [
            ['Carla Santos', 'carla.santos'],
            ['Miguel Reyes', 'miguel.reyes'],
            ['Ana Dela Cruz', 'ana.delacruz'],
            ['Paolo Garcia', 'paolo.garcia'],
            ['Liza Ramos', 'liza.ramos'],
            ['Benito Cruz', 'benito.cruz'],
            ['Rico Mendoza', 'rico.mendoza'],
            ['Tina Aquino', 'tina.aquino'],
        ];

        return array_map(
            fn (array $row): User => $this->account(
                $row[1].self::EMAIL_DOMAIN,
                $row[0],
                Role::Buyer,
                null,
                ['location' => 'General Trias, Cavite'],
            ),
            $rows,
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function account(string $email, string $name, Role $role, ?Farm $farm, array $extra = []): User
    {
        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null && $existing->hasRole(Role::SuperAdmin->value)) {
            return $existing;
        }

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => self::PASSWORD,
                'status' => UserStatus::Active,
                'approved_at' => now(),
                'email_verified_at' => now(),
                'farm_id' => $farm?->id,
                'phone' => null,
                'contact' => null,
                ...$extra,
            ],
        );

        $user->forceFill([
            'must_change_password' => false,
            'temporary_password_expires_at' => null,
            'phone' => null,
            'contact' => null,
        ])->save();

        $user->syncRoles($role->value);

        return $user->fresh();
    }

    private function paymentQr(User $seller): void
    {
        $seller->forceFill([
            'accepts_online_payment' => true,
            'payment_time_limit_hours' => 48,
        ])->save();

        if ($seller->paymentQrs()->exists()) {
            return;
        }

        $image = imagecreatetruecolor(320, 80);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefilledrectangle($image, 0, 0, 319, 79, $white);
        imagestring($image, 3, 8, 32, 'DEMO QR - not a real account', $black);
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        $path = 'payment-qrs/'.$seller->id.'/demo-qr.png';
        Storage::disk('local')->put($path, $png === false ? '' : $png);

        SellerPaymentQr::query()->create([
            'farmer_seller_id' => $seller->id,
            'wallet' => PaymentWallet::Gcash,
            'account_name' => $seller->shop_name ?: $seller->name,
            'account_last4' => '0000',
            'image_path' => $path,
        ]);
    }

    /**
     * @return array<string, CropType>
     */
    private function crops(): array
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
            $existing = CropType::query()
                ->whereNull('farm_id')
                ->where('is_active', true)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->first();

            if ($existing === null) {
                $existing = CropType::query()->where('slug', 'demo-'.$key)->first();
            }

            if ($existing === null) {
                $existing = CropType::query()->create([
                    'name' => $name,
                    'slug' => 'demo-'.$key,
                    'label_en' => $en,
                    'label_fil' => $fil,
                    'description' => $description,
                    'unit_of_measure' => $unit,
                    'floor_price' => $floor,
                    'max_discount' => $max,
                    'is_active' => true,
                ]);
            }

            $crops[$key] = $existing;
        }

        return $crops;
    }

    private function pyapKamatisFloor(Farm $farm, CropType $kamatis): void
    {
        if ((float) $kamatis->floor_price > 40) {
            return;
        }

        $this->priceOverrides->execute($farm, $kamatis, '40.00', null);
    }

    /**
     * @param  array<string, User>  $sellers
     * @param  array<string, CropType>  $crops
     * @return array<string, Listing>
     */
    private function listings(array $sellers, array $crops): array
    {
        $listings = [];

        foreach ($this->listingPlans() as $plan) {
            $seller = $sellers[$plan['seller']];
            $crop = $crops[$plan['crop']];
            $found = Listing::query()
                ->where('farmer_seller_id', $seller->id)
                ->where('crop_type_id', $crop->id)
                ->first();

            if ($found !== null) {
                if ($found->title !== $plan['title']) {
                    $found->forceFill(['title' => $plan['title']])->save();
                }

                $listings[$plan['key']] = $found->fresh();

                continue;
            }

            $guard = $this->priceGuards->forFarmId($seller->farm_id, $crop);
            $price = max($plan['price'], $guard->floor);

            $listing = $this->createListing->handle($seller, [
                'crop_type_id' => $crop->id,
                'title' => $plan['title'],
                'description' => $plan['description'],
                'unit' => $crop->unit_of_measure,
                'price_per_unit' => number_format($price, 2, '.', ''),
                'min_order_quantity' => $plan['min'],
                'order_step' => $plan['step'],
                'is_active' => true,
            ], null, $this->harvestPayload($this->stockFor($crop->unit_of_measure), false, $price, $crop->unit_of_measure));

            $listing->forceFill([
                'image_path' => $this->storePublicPhoto(
                    'listings/demo-'.$plan['key'].'.jpg',
                    self::CROP_PHOTOS[$plan['crop']] ?? 'tomato.jpg',
                ),
            ])->save();

            $listings[$plan['key']] = $listing->fresh();
        }

        return $listings;
    }

    /**
     * @return list<array{key: string, seller: string, crop: string, title: string, description: string, price: float, min: float, step: float, reserve: bool}>
     */
    private function listingPlans(): array
    {
        return [
            $this->plan('nena-kamatis', 'nena', 'kamatis', 'Kamatis, pula at hinog', 'Ripe red tomatoes, harvested Friday.', 55),
            $this->plan('nena-talong', 'nena', 'talong', 'Talong, mahaba', 'Long purple eggplant from the backyard plot.', 40, 1, 0.5),
            $this->plan('nena-sitaw', 'nena', 'sitaw', 'Sitaw, sariwa', 'Yard-long beans, tied in bundles.', 22),
            $this->plan('nena-pechay', 'nena', 'pechay', 'Pechay, bunot umaga', 'Leafy pechay, washed and bundled.', 18),
            $this->plan('tonyo-kalabasa', 'tonyo', 'kalabasa', 'Kalabasa, matamis', 'Orange squash, cut to order at the hall.', 35, 1, 0.5),
            $this->plan('tonyo-ampalaya', 'tonyo', 'ampalaya', 'Ampalaya, batang pitas', 'Young bitter gourd.', 50, 1, 0.5),
            $this->plan('tonyo-okra', 'tonyo', 'okra', 'Okra, malambot', 'Tender okra, picked the same morning.', 45),
            $this->plan('tonyo-mais', 'tonyo', 'mais', 'Mais, matamis', 'Sweet corn, sold by the ear.', 12),
            $this->plan('rosa-kamatis', 'rosa', 'kamatis', 'Kamatis, salad size', 'Medium tomatoes, firm for packing.', 58),
            $this->plan('rosa-sili', 'rosa', 'sili', 'Sili, anghang', 'Green chili and a little labuyo.', 150, 0.5, 0.5, true),
            $this->plan('rosa-sitaw', 'rosa', 'sitaw', 'Sitaw, mahaba', 'Long sitaw, good for ginisang sitaw.', 24),
            $this->plan('jun-talong', 'jun', 'talong', 'Talong, pang-torta', 'Firm eggplant for tortang talong.', 42),
            $this->plan('jun-kalabasa', 'jun', 'kalabasa', 'Kalabasa, pang-ginataan', 'Dense squash, sold by the kilo.', 38),
            $this->plan('jun-pechay', 'jun', 'pechay', 'Pechay, bagong ani', 'Morning-cut pechay bundles.', 20),
            $this->plan('liza-kamatis', 'liza', 'kamatis', 'Kamatis, Tanza plot', 'Salad tomatoes from the Tanza rows.', 52, 1, 1, true),
            $this->plan('liza-talong', 'liza', 'talong', 'Talong, Tanza', 'Firm eggplant for torta.', 40, 1, 0.5),
            $this->plan('liza-sitaw', 'liza', 'sitaw', 'Sitaw, Tanza', 'Bundled yard-long beans.', 22),
            $this->plan('pedro-kalabasa', 'pedro', 'kalabasa', 'Kalabasa, hillside', 'Orange squash from the hillside plot.', 36, 1, 0.5),
            $this->plan('pedro-ampalaya', 'pedro', 'ampalaya', 'Ampalaya, hillside', 'Young bitter gourd.', 48),
            $this->plan('pedro-okra', 'pedro', 'okra', 'Okra, hillside', 'Morning okra.', 42),
            $this->plan('mira-pechay', 'mira', 'pechay', 'Pechay, garden row', 'Washed pechay bundles.', 18),
            $this->plan('mira-mais', 'mira', 'mais', 'Mais, garden row', 'Sweet corn by the ear.', 12),
            $this->plan('mira-sili', 'mira', 'sili', 'Sili, garden row', 'Green chili, packed light.', 140, 0.5, 0.5),
            $this->plan('carla-kamatis', 'carla', 'kamatis', 'Kamatis, quiet rows', 'Ripe tomatoes from the quiet rows.', 56, 1, 1, true),
            $this->plan('carla-talong', 'carla', 'talong', 'Talong, quiet rows', 'Long eggplant.', 41, 1, 0.5),
            $this->plan('carla-pechay', 'carla', 'pechay', 'Pechay, quiet rows', 'Morning pechay.', 18),
            $this->plan('oscar-sitaw', 'oscar', 'sitaw', 'Sitaw, creek baskets', 'Sitaw tied the same morning.', 23),
            $this->plan('oscar-okra', 'oscar', 'okra', 'Okra, creek baskets', 'Tender okra.', 44),
            $this->plan('oscar-mais', 'oscar', 'mais', 'Mais, creek baskets', 'Sweet corn ears.', 12),
        ];
    }

    /**
     * @return array{key: string, seller: string, crop: string, title: string, description: string, price: float, min: float, step: float, reserve: bool}
     */
    private function plan(
        string $key,
        string $seller,
        string $crop,
        string $title,
        string $description,
        float $price,
        float $min = 1,
        float $step = 1,
        bool $reserve = false,
    ): array {
        return [
            'key' => $key,
            'seller' => $seller,
            'crop' => $crop,
            'title' => $title,
            'description' => $description,
            'price' => $price,
            'min' => $min,
            'step' => $step,
            'reserve' => $reserve,
        ];
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
            'liza-talong' => [TawadType::Flat, 5.00, null],
            'carla-talong' => [TawadType::Flat, 5.00, null],
        ];

        foreach ($rules as $key => [$type, $amount, $minQty]) {
            $listing = $listings[$key]->fresh(['cropType', 'farm.cropTypeOverrides']);
            $guard = $this->priceGuards->forFarmId($listing->farm_id, $listing->cropType);

            if (! $guard->allowsDiscount($amount) || ! $guard->allowsResultingUnitPrice($listing->price_per_unit, $amount)) {
                continue;
            }

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
     * @param  list<User>  $buyers
     * @param  array<string, Listing>  $listings
     */
    private function history(int $weeks, Carbon $start, array $buyers, array $listings): void
    {
        $pools = $this->salePools($listings);
        $ordersPerWeek = ['pyap' => 7, 'truofa' => 5, 'sanctuario' => 4];
        $reasons = [
            CancellationReason::BuyerCancelled,
            CancellationReason::SellerDeclined,
            CancellationReason::NoShow,
        ];
        $removalReasons = [
            StockRemovalReason::Spoiled,
            StockRemovalReason::Damaged,
            StockRemovalReason::SoldOutside,
        ];
        $counts = [];

        for ($week = 0; $week < $weeks; $week++) {
            $weekStart = $start->copy()->addWeeks($week);
            $events = [];

            $events[] = [$weekStart->copy()->setTime(6, 30, 0), function () use ($listings, $weekStart): void {
                foreach ($listings as $listing) {
                    $this->topUp($listing);
                }

                if ((int) $weekStart->format('W') % 4 === 0) {
                    $this->reprice($listings, $weekStart);
                }
            }];

            foreach ($pools as $farmKey => $keys) {
                $counts[$farmKey] = $counts[$farmKey] ?? 0;
                $orderCount = $ordersPerWeek[$farmKey] + mt_rand(-1, 1);

                for ($n = 0; $n < $orderCount; $n++) {
                    $counts[$farmKey]++;
                    $listing = $listings[$keys[($week * 3 + $n) % count($keys)]];
                    $buyer = $buyers[($week + $n + strlen($farmKey)) % count($buyers)];
                    $preference = $n % 3 === 1
                        ? FulfillmentPreference::SellerDelivers
                        : FulfillmentPreference::BuyerPickup;
                    $cancel = $counts[$farmKey] % 10 === 0
                        ? $reasons[(intdiv($counts[$farmKey], 10) - 1) % 3]
                        : null;
                    $online = $cancel === null
                        && $listing->farmerSeller->acceptsOnlinePayment()
                        && mt_rand(1, 10) <= 3;
                    $quantity = $this->orderQuantity($listing);
                    $placedAt = $weekStart->copy()
                        ->addDays(intdiv($n * 7, $orderCount))
                        ->setTime(7 + mt_rand(0, 10), mt_rand(0, 59), 0);

                    $events[] = [$placedAt, function () use ($buyer, $listing, $quantity, $preference, $cancel, $online): void {
                        $this->ensureStock($listing, $quantity);
                        $order = $this->place($buyer, $listing->fresh(), $quantity, $preference, $online);

                        if ($online) {
                            $order = $this->pay($order, $buyer, true);
                        }

                        $this->advance($order, $listing->farmerSeller, $buyer, $cancel);
                    }];
                }

                $walkIns = mt_rand(1, 2);

                for ($w = 0; $w < $walkIns; $w++) {
                    $walkListing = $listings[$keys[($week + $w * 5) % count($keys)]];
                    $walkQuantity = $this->orderQuantity($walkListing);
                    $events[] = [$weekStart->copy()->addDays(2 + $w * 3)->setTime(11, 30, 0), function () use ($walkListing, $walkQuantity): void {
                        $this->ensureStock($walkListing, $walkQuantity);
                        $fresh = $walkListing->fresh();
                        $this->walkIns->execute(
                            $fresh->farmerSeller,
                            $fresh,
                            $walkQuantity,
                            round((float) $fresh->price_per_unit * $walkQuantity, 2),
                            'Walk-in neighbor',
                            'Stall walk-in.',
                        );
                    }];
                }

                if ($week % 4 === 0) {
                    $removeListing = $listings[$keys[($week + 1) % count($keys)]];
                    $reason = $removalReasons[intdiv($week, 4) % 3];
                    $events[] = [$weekStart->copy()->addDays(4)->setTime(17, 0, 0), function () use ($removeListing, $reason): void {
                        $fresh = $removeListing->fresh();

                        if ($fresh->sellableQuantity() < 2) {
                            return;
                        }

                        $this->removeStock->handle($fresh, '1.00', $reason, 'Demo stock removal.', $fresh->farmerSeller);
                    }];
                }
            }

            usort($events, fn (array $a, array $b): int => $a[0] <=> $b[0]);

            foreach ($events as [$when, $event]) {
                $this->at($when, $event);
            }
        }

        $this->scriptedPayments($start, $listings, $buyers);
        $this->pastReservations($start, $weeks, $listings, $buyers);
    }

    /**
     * Stock a seller keeps on hand: a weekly harvest tops the listing back up
     * to this, so harvests track sales instead of piling up.
     */
    private function targetStock(Listing $listing): float
    {
        return $this->stockFor($listing->unit);
    }

    private function stockFor(?ListingUnit $unit): float
    {
        return match ($unit) {
            ListingUnit::Piece => 24.0,
            ListingUnit::Bundle => 12.0,
            default => 10.0,
        };
    }

    /**
     * A believable basket: the listing minimum plus a few steps.
     */
    private function orderQuantity(Listing $listing): float
    {
        $min = max(0.5, (float) $listing->min_order_quantity);
        $step = max(0.5, (float) $listing->order_step);
        $max = match ($listing->unit) {
            ListingUnit::Piece => 6.0,
            ListingUnit::Bundle => 4.0,
            default => 3.0,
        };
        $steps = max(0, (int) floor(($max - $min) / $step));

        return $min + $step * mt_rand(0, $steps);
    }

    /**
     * Weekly pick: whatever sold since the last harvest is picked again.
     */
    private function topUp(Listing $listing): void
    {
        $fresh = $listing->fresh();

        if ($fresh->isUpcoming()) {
            return;
        }

        $target = $this->targetStock($fresh);
        $sellable = $fresh->sellableQuantity();

        if ($sellable > $target - 1) {
            return;
        }

        $this->harvest($fresh, ceil(($target - $sellable) * 2) / 2);
    }

    /**
     * An extra pick when a sale would otherwise run past the stock.
     */
    private function ensureStock(Listing $listing, float $quantity): void
    {
        $fresh = $listing->fresh();

        if ($fresh->sellableQuantity() >= $quantity) {
            return;
        }

        $this->harvest($fresh, ceil(($this->targetStock($fresh) + $quantity - $fresh->sellableQuantity()) * 2) / 2);
    }

    private function harvest(Listing $listing, float $good): void
    {
        if ($good <= 0) {
            return;
        }

        $this->addStock->handle(
            $listing,
            $this->harvestPayload($good, mt_rand(1, 3) === 1, (float) $listing->price_per_unit, $listing->unit),
            $listing->farmerSeller,
        );
    }

    /**
     * @param  array<string, Listing>  $listings
     * @return array<string, list<string>>
     */
    private function salePools(array $listings): array
    {
        $pools = ['pyap' => [], 'truofa' => [], 'sanctuario' => []];

        foreach ($this->listingPlans() as $plan) {
            if ($plan['reserve']) {
                continue;
            }

            $farmKey = match ($plan['seller']) {
                'liza', 'pedro', 'mira' => 'truofa',
                'carla', 'oscar' => 'sanctuario',
                default => 'pyap',
            };
            $pools[$farmKey][] = $plan['key'];
        }

        return $pools;
    }

    /**
     * @param  array<string, Listing>  $listings
     */
    private function reprice(array $listings, Carbon $when): void
    {
        $rainy = in_array((int) $when->format('n'), [7, 8, 9], true);

        foreach ($this->listingPlans() as $plan) {
            if ($plan['reserve']) {
                continue;
            }

            $listing = $listings[$plan['key']]->fresh(['cropType']);
            $guard = $this->priceGuards->forFarmId($listing->farm_id, $listing->cropType);
            $price = $rainy ? round($plan['price'] * 1.12, 2) : $plan['price'];
            $price = max($price, $guard->floor);

            if (abs((float) $listing->price_per_unit - $price) < 0.001) {
                continue;
            }

            $this->updateListing->handle($listing, [
                'price_per_unit' => number_format($price, 2, '.', ''),
            ]);
        }
    }

    /**
     * @param  array<string, Listing>  $listings
     * @param  list<User>  $buyers
     */
    private function scriptedPayments(Carbon $start, array $listings, array $buyers): void
    {
        $when = $start->copy()->addDays(3)->setTime(9, 0, 0);

        if ($when->greaterThan($this->end)) {
            $when = $start->copy()->setTime(9, 0, 0);
        }

        $scripts = [
            [$listings['nena-talong'], $buyers[0], 'reject'],
            [$listings['liza-talong'], $buyers[1], 'reject'],
            [$listings['carla-talong'], $buyers[2], 'refund'],
        ];

        foreach ($scripts as [$listing, $buyer, $mode]) {
            $this->at($when, function () use ($listing, $buyer, $mode): void {
                $this->ensureStock($listing, 1);
                $fresh = $listing->fresh();
                $order = $this->place($buyer, $fresh, 1, FulfillmentPreference::BuyerPickup, true);

                if ($mode === 'reject') {
                    $order = $this->pay($order, $buyer, true, true);
                    $this->advance($order, $fresh->farmerSeller, $buyer, null);

                    return;
                }

                $order = $this->pay($order, $buyer, true);
                $seller = $fresh->farmerSeller;
                $this->orders->transition(
                    $order,
                    OrderStatus::Cancelled,
                    $seller,
                    'Demo paid order cancelled for a refund.',
                    CancellationReason::SellerDeclined,
                );
                $this->refunds->handle($seller, $order->fresh(), $this->reference());
            });
        }
    }

    /**
     * @param  array<string, Listing>  $listings
     * @param  list<User>  $buyers
     */
    private function pastReservations(Carbon $start, int $weeks, array $listings, array $buyers): void
    {
        $span = max(7, $weeks * 7);
        $days = [
            max(3, (int) floor($span / 3)),
            max(4, (int) floor(($span * 2) / 3)),
        ];
        $keys = ['rosa-sili', 'liza-kamatis', 'carla-kamatis'];
        $buyerIndex = 0;

        foreach ($keys as $key) {
            foreach ($days as $daysAgo) {
                $reserveAt = $this->end->copy()->subDays($daysAgo)->setTime(8, 0, 0);

                if ($reserveAt->lessThan($start)) {
                    $reserveAt = $start->copy()->addHours(4);
                }

                $buyer = $buyers[$buyerIndex % count($buyers)];
                $buyerIndex++;
                $listing = $listings[$key];
                $openAt = $reserveAt->copy()->addDays(2);

                if ($openAt->greaterThan($this->end->copy()->subHour())) {
                    $openAt = $this->end->copy()->subHour();
                }

                $this->at($reserveAt, function () use ($listing, $buyer, $openAt): void {
                    $this->updateListing->handle($listing->fresh(), [
                        'available_from' => $openAt->toDateTimeString(),
                    ]);
                    $result = $this->reserveListing->handle(
                        $buyer,
                        $listing->id,
                        1,
                        FulfillmentPreference::BuyerPickup,
                        'Demo reservation',
                        'proof',
                    );
                    $reservation = $result['reservation']->fresh();
                    $qrId = (int) ($reservation->payment_qr_ids[0] ?? 0);
                    $proof = $this->submitReservationProof->handle(
                        $buyer,
                        $reservation,
                        $this->reference(),
                        (float) $reservation->line_total,
                        $qrId,
                    );
                    $this->reviewReservationProof->handle(
                        $listing->fresh()->farmerSeller,
                        $reservation->fresh(),
                        $proof,
                        'accept',
                    );
                });

                $this->at($openAt->copy()->addMinute(), function () use ($listing, $buyer): void {
                    $this->openDue->handle();
                    $order = Order::query()
                        ->where('buyer_id', $buyer->id)
                        ->where('farmer_seller_id', $listing->farmer_seller_id)
                        ->latest('id')
                        ->first();

                    if ($order === null) {
                        throw new RuntimeException('A paid reservation did not become an order.');
                    }

                    $this->advance($order, $listing->fresh()->farmerSeller, $buyer, null);
                    $this->updateListing->handle($listing->fresh(), [
                        'available_from' => null,
                    ]);
                });
            }
        }
    }

    /**
     * @param  array<string, Farm>  $farms
     * @param  array<string, User>  $sellers
     * @param  list<User>  $buyers
     * @param  array<string, Listing>  $listings
     */
    private function currentState(array $farms, array $sellers, array $buyers, array $listings): void
    {
        $moment = $this->end->copy()->subHour();

        $this->at($moment->copy()->subHour(), function () use ($listings): void {
            foreach ($listings as $listing) {
                $this->topUp($listing);
            }
        });

        $live = [
            'pyap' => [
                [$listings['nena-pechay'], $buyers[0], OrderStatus::Placed],
                [$listings['tonyo-mais'], $buyers[1], OrderStatus::Confirmed],
                [$listings['jun-pechay'], $buyers[2], OrderStatus::Ready],
            ],
            'truofa' => [
                [$listings['pedro-okra'], $buyers[3], OrderStatus::Placed],
                [$listings['mira-pechay'], $buyers[4], OrderStatus::Confirmed],
                [$listings['pedro-ampalaya'], $buyers[5], OrderStatus::Ready],
            ],
            'sanctuario' => [
                [$listings['oscar-okra'], $buyers[6], OrderStatus::Placed],
                [$listings['oscar-sitaw'], $buyers[7], OrderStatus::Confirmed],
                [$listings['oscar-mais'], $buyers[0], OrderStatus::Ready],
            ],
        ];

        foreach ($live as $rows) {
            foreach ($rows as [$listing, $buyer, $status]) {
                $this->at($moment, function () use ($listing, $buyer, $status): void {
                    $this->ensureStock($listing, 1);
                    $fresh = $listing->fresh();
                    $order = $this->place($buyer, $fresh, 1, FulfillmentPreference::BuyerPickup, false);
                    $steps = match ($status) {
                        OrderStatus::Confirmed => [OrderStatus::Confirmed],
                        OrderStatus::Ready => [OrderStatus::Confirmed, OrderStatus::Ready],
                        default => [],
                    };

                    foreach ($steps as $step) {
                        $order = $this->orders->transition($order->fresh(), $step, $fresh->farmerSeller);
                    }
                });
            }
        }

        $this->at($moment, function () use ($listings, $buyers): void {
            $this->ensureStock($listings['nena-sitaw'], 1);
            $listing = $listings['nena-sitaw']->fresh();
            $order = $this->place($buyers[3], $listing, 1, FulfillmentPreference::BuyerPickup, true);
            $this->pay($order, $buyers[3], false);
        });

        $opens = $this->end->copy()->addDays(5);
        $reservations = [
            ['rosa-sili', $buyers[4]],
            ['rosa-sili', $buyers[5]],
            ['liza-kamatis', $buyers[6]],
            ['carla-kamatis', $buyers[7]],
        ];

        foreach ($reservations as [$key, $buyer]) {
            $this->at($moment, function () use ($listings, $key, $buyer, $opens): void {
                $listing = $listings[$key]->fresh();
                $this->updateListing->handle($listing, [
                    'available_from' => $opens->toDateTimeString(),
                ]);
                $result = $this->reserveListing->handle(
                    $buyer,
                    $listing->id,
                    1,
                    FulfillmentPreference::BuyerPickup,
                    'Upcoming harvest reservation',
                    'proof',
                );
                $reservation = $result['reservation']->fresh();
                $qrId = (int) ($reservation->payment_qr_ids[0] ?? 0);
                $proof = $this->submitReservationProof->handle(
                    $buyer,
                    $reservation,
                    $this->reference(),
                    (float) $reservation->line_total,
                    $qrId,
                );
                $this->reviewReservationProof->handle(
                    $listing->farmerSeller,
                    $reservation->fresh(),
                    $proof,
                    'accept',
                );
            });
        }
    }

    private function place(
        User $buyer,
        Listing $listing,
        float $quantity,
        FulfillmentPreference $preference,
        bool $online,
    ): Order {
        CartItem::query()->where('buyer_id', $buyer->id)->delete();
        CartItem::query()->create([
            'buyer_id' => $buyer->id,
            'listing_id' => $listing->id,
            'quantity' => $quantity,
        ]);

        $orders = $this->checkout->checkout(
            $buyer,
            $preference,
            $preference === FulfillmentPreference::SellerDelivers ? 'Demo delivery' : null,
            $online ? [[
                'seller_id' => $listing->farmer_seller_id,
                'method' => PaymentMethod::OnlineTransfer->value,
            ]] : [],
            $online ? 'proof' : null,
        );

        $order = $orders->first();

        if (! $order instanceof Order) {
            throw new RuntimeException('Checkout created no order for '.$listing->title.'.');
        }

        return $order;
    }

    private function pay(Order $order, User $buyer, bool $accept, bool $rejectFirst = false): Order
    {
        $seller = User::query()->findOrFail($order->farmer_seller_id);

        if ($rejectFirst) {
            $order = $this->reviewProof->handle(
                $seller,
                $order,
                $this->submitProof->handle(
                    $buyer,
                    $order,
                    $this->reference(),
                    (float) $order->total,
                    (int) ($order->payment_qr_ids[0] ?? 0),
                ),
                'reject',
                PaymentRejectionReason::WrongAmount,
                'Demo reject, please resend.',
            );
        }

        $proof = $this->submitProof->handle(
            $buyer,
            $order->fresh(),
            $this->reference(),
            (float) $order->fresh()->total,
            (int) ($order->fresh()->payment_qr_ids[0] ?? 0),
        );

        if (! $accept) {
            return $order->fresh();
        }

        return $this->reviewProof->handle($seller, $order->fresh(), $proof, 'accept');
    }

    private function advance(Order $order, User $seller, User $buyer, ?CancellationReason $reason): void
    {
        if ($reason === CancellationReason::BuyerCancelled) {
            $this->orders->transition($order->fresh(), OrderStatus::Cancelled, $buyer, 'Demo buyer cancel.', $reason);

            return;
        }

        if ($reason === CancellationReason::SellerDeclined) {
            $this->orders->transition($order->fresh(), OrderStatus::Cancelled, $seller, 'Demo seller decline.', $reason);

            return;
        }

        $order = $this->orders->transition($order->fresh(), OrderStatus::Confirmed, $seller);
        $order = $this->orders->transition($order, OrderStatus::Ready, $seller);

        if ($reason === CancellationReason::NoShow) {
            $this->orders->transition($order, OrderStatus::Cancelled, $seller, 'Demo no-show.', $reason);

            return;
        }

        $this->orders->transition($order, OrderStatus::Completed, $seller, amountReceived: (float) $order->total);
    }

    /**
     * @return array{
     *     harvested_on: string,
     *     quantity_harvested: string,
     *     quantity_rejected: string,
     *     quantity_good: string,
     *     rejection_reason: ?string,
     *     rejection_note: ?string,
     *     production_cost: ?string,
     *     cost_breakdown: null
     * }
     */
    private function harvestPayload(float $good, bool $reject, float $unitPrice, ?ListingUnit $unit): array
    {
        $rejectReasons = [
            HarvestRejectionReason::Pests,
            HarvestRejectionReason::BruisedDamaged,
            HarvestRejectionReason::Undersized,
            HarvestRejectionReason::Spoiled,
        ];
        $whole = in_array($unit, [ListingUnit::Piece, ListingUnit::Bundle], true);
        $rejected = match (true) {
            ! $reject => 0.0,
            $whole => max(1.0, round($good * 0.12)),
            default => max(0.5, round($good * 0.12 * 2) / 2),
        };
        $harvested = $good + $rejected;
        $withCost = mt_rand(1, 10) <= 7;
        $costShare = mt_rand(30, 42) / 100;

        return [
            'harvested_on' => now()->toDateString(),
            'quantity_harvested' => number_format($harvested, 2, '.', ''),
            'quantity_rejected' => number_format($rejected, 2, '.', ''),
            'quantity_good' => number_format($good, 2, '.', ''),
            'rejection_reason' => $reject ? $rejectReasons[mt_rand(0, count($rejectReasons) - 1)]->value : null,
            'rejection_note' => $reject ? 'Sorted out at harvest.' : null,
            'production_cost' => $withCost ? number_format(round($harvested * $unitPrice * $costShare, 2), 2, '.', '') : null,
            'cost_breakdown' => null,
        ];
    }

    private function reviews(): void
    {
        $comments = [
            'Sariwa. Babalik ako next week.',
            'Mabait ang seller, goods ang gulay.',
            'On time sa pickup. Salamat.',
            'Medyo maliit ang bundle, pero fresh.',
            'Masarap ang kamatis, salad agad.',
            'Talong goods for torta.',
            'Fair price, walang abala.',
            'Pechay perked up after a soak.',
        ];

        $completed = $this->demoOrders()
            ->where('status', OrderStatus::Completed)
            ->whereNotNull('buyer_id')
            ->where('source', '!=', OrderSource::WalkIn)
            ->orderBy('id')
            ->get();

        foreach ($completed->values() as $index => $order) {
            if ($index % 2 !== 0) {
                continue;
            }

            $rating = match (true) {
                $index === 0 => 2,
                $index % 9 === 0 => 3,
                default => 4 + ($index % 2),
            };

            Review::query()->firstOrCreate(
                ['order_id' => $order->id],
                [
                    'buyer_id' => $order->buyer_id,
                    'farmer_seller_id' => $order->farmer_seller_id,
                    'rating' => $rating,
                    'comment' => $comments[$index % count($comments)],
                ],
            );
        }
    }

    private function chats(): void
    {
        $orders = $this->demoOrders()
            ->where('status', OrderStatus::Completed)
            ->whereNotNull('buyer_id')
            ->where('source', '!=', OrderSource::WalkIn)
            ->orderBy('id')
            ->limit(3)
            ->get();

        foreach ($orders as $order) {
            $conversation = StallConversation::query()->firstOrCreate([
                'buyer_id' => $order->buyer_id,
                'farmer_seller_id' => $order->farmer_seller_id,
            ]);

            StallMessage::query()->firstOrCreate(
                [
                    'stall_conversation_id' => $conversation->id,
                    'body' => 'Po, sariwa ba today?',
                ],
                [
                    'user_id' => $order->buyer_id,
                    'order_id' => $order->id,
                ],
            );
            StallMessage::query()->firstOrCreate(
                [
                    'stall_conversation_id' => $conversation->id,
                    'body' => 'Opo, this morning lang inani.',
                ],
                [
                    'user_id' => $order->farmer_seller_id,
                    'order_id' => $order->id,
                ],
            );
        }
    }

    /**
     * @param  array<string, Farm>  $farms
     * @param  array<string, User>  $editors
     */
    private function announcements(array $farms, array $editors): void
    {
        FarmAnnouncement::query()->updateOrCreate(
            ['farm_id' => $farms['pyap']->id, 'title' => 'Saturday harvest is on — come early'],
            [
                'author_id' => $editors['pyap']->id,
                'body' => 'Pickup at the PYAP hall, 6:00 AM to 10:00 AM. Bring cash. Walk-ins welcome after reserved orders are packed.',
                'audience' => AnnouncementAudience::Public,
                'starts_at' => now()->subDays(2)->startOfDay(),
                'ends_at' => now()->addDays(12)->endOfDay(),
                'is_pinned' => true,
            ],
        );

        FarmAnnouncement::query()->updateOrCreate(
            ['farm_id' => $farms['pyap']->id, 'title' => 'Members: extra pechay crates tomorrow'],
            [
                'author_id' => $editors['pyap']->id,
                'body' => 'If you already ordered pechay, we have two extra crates. Message your seller before 7am.',
                'audience' => AnnouncementAudience::Members,
                'starts_at' => now()->addDays(2)->startOfDay(),
                'ends_at' => now()->addDays(4)->endOfDay(),
                'is_pinned' => false,
            ],
        );

        FarmAnnouncement::query()->updateOrCreate(
            ['farm_id' => $farms['sanctuario']->id, 'title' => 'Sanctuario harvest morning'],
            [
                'author_id' => $editors['sanctuario']->id,
                'body' => 'Fresh bundles are packed in the morning. Message your seller for the pickup time.',
                'audience' => AnnouncementAudience::Public,
                'starts_at' => now()->subDay()->startOfDay(),
                'ends_at' => now()->addDays(12)->endOfDay(),
                'is_pinned' => false,
            ],
        );
    }

    /**
     * @param  array<string, Farm>  $farms
     * @param  array<string, User>  $editors
     * @param  array<string, CropType>  $crops
     */
    private function articles(array $farms, array $editors, array $crops): void
    {
        $rows = [
            [
                'farm' => 'pyap',
                'slug' => 'kamatis-in-cavite-heat',
                'title' => 'Keeping kamatis productive in Cavite heat',
                'excerpt' => 'Mulch, water at the base, and pick often so plants keep setting.',
                'body' => "Mulch the beds to hold moisture through General Trias dry spells.\n\nWater at the base early in the morning. Pick ripe fruit often so plants keep setting. This is general crop-care guidance, not a spray calendar.",
                'category' => ArticleCategory::CropCare,
                'tags' => ['kamatis'],
            ],
            [
                'farm' => 'pyap',
                'slug' => 'ampalaya-fruit-fly-watch',
                'title' => 'Watching fruit fly on ampalaya',
                'excerpt' => 'Bag young fruit and check the underside of leaves weekly.',
                'body' => "Fruit fly marks show as soft spots on young ampalaya.\n\nBag fruit early and pick damaged fruit off the plot. AniHow does not schedule sprays; this note is a field reminder only.",
                'category' => ArticleCategory::PestManagement,
                'tags' => ['ampalaya'],
            ],
            [
                'farm' => 'pyap',
                'slug' => 'sitaw-and-pechay-after-rain',
                'title' => 'Sitaw and pechay after a Cavite downpour',
                'excerpt' => 'Stake sitaw, drain pechay beds, and harvest wet leaves the same morning.',
                'body' => "After heavy rain, sitaw vines sag and pechay beds hold water.\n\nRe-stake sitaw and harvest wet leaves the same morning. Guidance only — not a weather forecast.",
                'category' => ArticleCategory::CropCare,
                'tags' => ['sitaw', 'pechay'],
            ],
            [
                'farm' => 'sanctuario',
                'slug' => 'sanctuario-kamatis-after-rain',
                'title' => 'Kamatis after rain at Sanctuario',
                'excerpt' => 'Stake the vines and pick wet fruit the same morning.',
                'body' => "After rain, stake kamatis and pick fruit that is already wet.\n\nThis is general crop-care guidance, not a spray calendar.",
                'category' => ArticleCategory::CropCare,
                'tags' => ['kamatis'],
            ],
        ];

        foreach ($rows as $row) {
            $article = CropCareArticle::query()->updateOrCreate(
                ['farm_id' => $farms[$row['farm']]->id, 'slug' => $row['slug']],
                [
                    'created_by' => $editors[$row['farm']]->id,
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

    private function faq(Farm $farm): void
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

    private function historyExists(): bool
    {
        return $this->demoOrders()->exists();
    }

    /**
     * Orders sold by demo sellers only, so fixture orders never get demo reviews or chats.
     *
     * @return Builder<Order>
     */
    private function demoOrders(): Builder
    {
        return Order::query()
            ->whereHas('farmerSeller', fn (Builder $query): Builder => $query->where('email', 'like', '%'.self::EMAIL_DOMAIN));
    }

    private function reference(): string
    {
        $this->references++;

        return 'DEMO'.str_pad((string) $this->references, 6, '0', STR_PAD_LEFT);
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

    private function storePublicPhoto(string $path, string $filename): string
    {
        $source = public_path('images/produce/'.$filename);

        if (is_file($source)) {
            app(ImageVariants::class)->delete($path);
            ListingStorage::disk()->put($path, File::get($source));
            app(ImageVariants::class)->backfill($path);

            return $path;
        }

        return $path;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function summary(): array
    {
        $demo = User::query()->where(function ($query): void {
            $query->where('email', 'like', '%'.self::EMAIL_DOMAIN)
                ->orWhere('email', 'editor01@gmail.com');
        });

        $orders = Order::query()->whereHas(
            'farmerSeller',
            fn ($query) => $query->where('email', 'like', '%'.self::EMAIL_DOMAIN),
        );

        $byStatus = (clone $orders)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $statusLine = collect(OrderStatus::cases())
            ->map(fn (OrderStatus $status): string => $status->value.' '.(int) ($byStatus[$status->value] ?? 0))
            ->implode(', ');

        $roleLine = collect(Role::cases())
            ->map(function (Role $role) use ($demo): string {
                $count = (clone $demo)->role($role->value)->count();

                return $role->value.' '.$count;
            })
            ->implode(', ');

        $earliest = (clone $orders)->min('created_at');
        $latest = (clone $orders)->max('created_at');
        $range = $earliest === null
            ? 'none'
            : Carbon::parse($earliest)->toDateString().' to '.Carbon::parse($latest)->toDateString();

        $sellerIds = User::query()
            ->where('email', 'like', '%'.self::EMAIL_DOMAIN)
            ->role(Role::FarmerSeller->value)
            ->pluck('id');

        return [
            ['Farms', (string) Farm::query()->whereIn('slug', [
                ClientFarms::PYAP_SLUG,
                ClientFarms::TRUOFA_SLUG,
                ClientFarms::SANCTUARIO_SLUG,
            ])->count()],
            ['Accounts', $roleLine],
            ['Orders', $statusLine],
            ['Walk-ins', (string) (clone $orders)->where('source', OrderSource::WalkIn)->count()],
            ['Harvest records', (string) HarvestRecord::query()->whereIn('farmer_seller_id', $sellerIds)->count()],
            ['Stock removals', (string) StockRemoval::query()->whereIn('farmer_seller_id', $sellerIds)->count()],
            ['Reservations', (string) Reservation::query()->whereIn('farmer_seller_id', $sellerIds)->count()],
            ['Payment proofs', (string) PaymentProof::query()->whereIn('farmer_seller_id', $sellerIds)->count()],
            ['Reviews', (string) Review::query()->whereIn('farmer_seller_id', $sellerIds)->count()],
            ['Date range', $range],
        ];
    }
}
