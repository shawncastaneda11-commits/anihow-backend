<?php

namespace App\Actions\Testing;

use App\Actions\Listings\CreateListingAction;
use App\Actions\Listings\WriteHarvestRecord;
use App\Actions\Privacy\RequestAccountDeletionAction;
use App\Actions\Reports\SubmitReportAction;
use App\Actions\Reservations\ReserveListing;
use App\Enums\AccountDeletionStatus;
use App\Enums\AnnouncementAudience;
use App\Enums\FulfillmentPreference;
use App\Enums\HarvestRecordKind;
use App\Enums\ListingUnit;
use App\Enums\OrderStatus;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Enums\Role;
use App\Enums\TawadType;
use App\Enums\UserStatus;
use App\Http\Controllers\Api\Cart\CartController;
use App\Http\Requests\Api\Cart\StoreCartItemRequest;
use App\Models\CropType;
use App\Models\Farm;
use App\Models\FarmAnnouncement;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Report;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\TawadRule;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\OrderStateMachine;
use App\Support\HarvestInput;
use App\Support\Pricing\PriceGuardResolver;
use App\Support\Pricing\UnitConverter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CreateTestFixtures
{
    /** @var list<array{item: string, status: string}> */
    private array $rows = [];

    private bool $dryRun = false;

    /** @var array<string, true> */
    private array $planned = [];

    public function __construct(
        private CheckoutService $checkout,
        private OrderStateMachine $orders,
        private ReserveListing $reserveListing,
        private SubmitReportAction $reports,
        private RequestAccountDeletionAction $deletionRequests,
        private CreateListingAction $createListing,
        private WriteHarvestRecord $harvestRecords,
        private PriceGuardResolver $priceGuards,
        private UnitConverter $units,
    ) {}

    /**
     * @return list<array{item: string, status: string}>
     */
    public function handle(bool $dryRun = false): array
    {
        $this->rows = [];
        $this->planned = [];
        $this->dryRun = $dryRun;

        $pyap = $this->farm('pyap-manggahan-chapter', 'PYAP Manggahan Chapter', 'General Trias', 'Manggahan');
        $tonyo = $this->farm('mang-tonyo-farm', 'Mang Tonyo Farm', 'General Trias', null);
        $testFarm = $this->farm('anihow-test-farm', 'AniHow Test Farm', 'General Trias', null);

        $admin = $this->account('admin01@gmail.com', 'Admin 01', 'Admin@1234', Role::SuperAdmin, null, UserStatus::Active);
        $editor = $this->editor('editor01@gmail.com', 'Editor 01', 'Editor@1234', $pyap, 'pyap-manggahan-chapter', UserStatus::Active, null);
        $this->editor('susp03@gmail.com', 'Suspended Editor', 'Editor@1234', $testFarm, 'anihow-test-farm', UserStatus::Suspended, 'Test account');

        $jun = $this->account('kuyajun@gmail.com', 'Kuya Jun', 'Seller@1234', Role::FarmerSeller, $pyap, UserStatus::Active, 'Kuya Jun Harvest', true);
        $nena = $this->account('alingnena@gmail.com', 'Aling Nena', 'Seller@1234', Role::FarmerSeller, $pyap, UserStatus::Active, 'Aling Nena Produce', false);
        $this->account('pending01@gmail.com', 'Pending Seller', 'Seller@1234', Role::FarmerSeller, $pyap, UserStatus::Pending);
        $this->account('susp02@gmail.com', 'Suspended Seller', 'Seller@1234', Role::FarmerSeller, $pyap, UserStatus::Suspended, suspensionReason: 'Test account');
        $buyer01 = $this->account('buyer01@gmail.com', 'Buyer 01', 'Buyer@1234', Role::Buyer, null, UserStatus::Active);
        $buyer02 = $this->account('buyer02@gmail.com', 'Buyer 02', 'Buyer@1234', Role::Buyer, null, UserStatus::Active);
        $delete01 = $this->account('delete01@gmail.com', 'Delete 01', 'Buyer@1234', Role::Buyer, null, UserStatus::Active);
        $this->account('susp01@gmail.com', 'Suspended Buyer', 'Buyer@1234', Role::Buyer, null, UserStatus::Suspended, suspensionReason: 'Test account');

        $pechay = $this->listing($jun, 'kuyajun@gmail.com', $pyap, 'Pechay, sariwa', ['Pechay'], ListingUnit::Bundle, 20.00, 30, 1, 1, null, null);
        $kalabasa = $this->listing($jun, 'kuyajun@gmail.com', $pyap, 'Kalabasa, pangkare-kare', ['Kalabasa', 'Squash'], ListingUnit::Kilogram, 38.00, 25, 0.5, 0.25, null, null);
        $talong = $this->listing($jun, 'kuyajun@gmail.com', $pyap, 'Talong, pantatong', ['Talong', 'Eggplant'], ListingUnit::Kilogram, 42.00, 20, 1, 1, null, [
            'type' => TawadType::Flat,
            'amount' => 5.00,
            'min_quantity' => null,
        ]);
        $this->listing($jun, 'kuyajun@gmail.com', $pyap, 'Kamatis, bagong pitas', ['Kamatis', 'Tomato'], ListingUnit::Kilogram, 55.00, 30, 1, 1, null, [
            'type' => TawadType::MinimumQuantity,
            'amount' => 15.00,
            'min_quantity' => 5,
        ]);
        $okraNext = $this->listing($jun, 'kuyajun@gmail.com', $pyap, 'Okra (next week)', ['Okra'], ListingUnit::Kilogram, 30.00, 20, 1, 1, 7, null);
        $okra = $this->listing($nena, 'alingnena@gmail.com', $pyap, 'Okra Sariwa', ['Okra'], ListingUnit::Kilogram, 25.00, 15, 1, 1, null, null);
        $squash = $this->listing($nena, 'alingnena@gmail.com', $pyap, 'squashy baby', ['Squash', 'Kalabasa'], ListingUnit::Kilogram, 20.00, 12, 1, 1, 3, null);

        $this->reservation($buyer02, 'buyer02@gmail.com', $squash, 'squashy baby', 2);
        $this->reservation($buyer02, 'buyer02@gmail.com', $okraNext, 'Okra (next week)', 3);

        $this->order($buyer01, 'buyer01@gmail.com', $jun, $pechay, 'Pechay, sariwa', 2, true, null);
        $orderB = $this->order($buyer01, 'buyer01@gmail.com', $nena, $okra, 'Okra Sariwa', 1, true, [
            'rating' => 1,
            'comment' => 'Ang bagal, walang kwentang seller!!',
        ]);
        $this->order($buyer01, 'buyer01@gmail.com', $jun, $kalabasa, 'Kalabasa, pangkare-kare', 1, false, null);

        $this->reportListing($buyer02, 'buyer02@gmail.com', $talong, 'Talong, pantatong');
        $this->reportReview($buyer02, 'buyer02@gmail.com', $orderB);
        $this->deletionRequest($delete01, 'delete01@gmail.com');
        $this->announcement($pyap, $editor);

        return $this->rows;
    }

    private function farm(string $slug, string $name, string $municipality, ?string $barangay): ?Farm
    {
        $item = "Farm {$name}";
        $farm = Farm::query()->where('slug', $slug)->first()
            ?? Farm::query()->where('name', $name)->first();

        if ($farm !== null) {
            $this->record($item, 'exists');

            return $farm;
        }

        if ($this->dryRun) {
            $this->plan('farm:'.$slug);
            $this->record($item, 'would create');

            return null;
        }

        $farm = Farm::query()->create([
            'name' => $name,
            'slug' => $slug,
            'municipality' => $municipality,
            'barangay' => $barangay,
            'is_active' => true,
        ]);
        $this->record($item, 'created');

        return $farm;
    }

    private function editor(
        string $email,
        string $name,
        string $password,
        ?Farm $farm,
        string $farmSlug,
        UserStatus $status,
        ?string $suspensionReason,
    ): ?User {
        $existing = User::withTrashed()->where('email', $email)->first();

        if ($existing !== null) {
            $this->record($email, 'exists');

            return $existing->trashed() ? null : $existing;
        }

        if ($farm !== null) {
            $editor = $farm->contentEditor()->first();

            if ($editor !== null && $editor->email !== $email) {
                $this->record($email, "skipped: farm already has Content Editor {$editor->email}");

                return null;
            }
        }

        return $this->createAccount($email, $name, $password, Role::ContentEditor, $farm, $farmSlug, $status, null, false, $suspensionReason);
    }

    private function account(
        string $email,
        string $name,
        string $password,
        Role $role,
        ?Farm $farm,
        UserStatus $status,
        ?string $shopName = null,
        bool $acceptsOnlinePayment = false,
        ?string $suspensionReason = null,
    ): ?User {
        $existing = User::withTrashed()->where('email', $email)->first();

        if ($existing !== null) {
            $this->record($email, 'exists');

            return $existing->trashed() ? null : $existing;
        }

        $farmSlug = $farm?->slug;

        return $this->createAccount($email, $name, $password, $role, $farm, $farmSlug, $status, $shopName, $acceptsOnlinePayment, $suspensionReason);
    }

    private function createAccount(
        string $email,
        string $name,
        string $password,
        Role $role,
        ?Farm $farm,
        ?string $farmSlug,
        UserStatus $status,
        ?string $shopName,
        bool $acceptsOnlinePayment,
        ?string $suspensionReason,
    ): ?User {
        if ($farm === null && $farmSlug !== null && ! $this->wasPlanned('farm:'.$farmSlug) && $role !== Role::SuperAdmin && $role !== Role::Buyer) {
            $this->record($email, 'skipped: farm is missing');

            return null;
        }

        if (! \Spatie\Permission\Models\Role::query()->where('name', $role->value)->exists()) {
            $this->record($email, "skipped: role {$role->value} is not seeded");

            return null;
        }

        if ($this->dryRun) {
            $this->plan('user:'.$email);
            $this->record($email, 'would create');

            return null;
        }

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'farm_id' => $farm?->id,
            'shop_name' => $shopName,
            'accepts_online_payment' => $acceptsOnlinePayment,
            'status' => $status,
            'email_verified_at' => now(),
            'suspended_at' => $status === UserStatus::Suspended ? now() : null,
            'suspension_reason' => $status === UserStatus::Suspended ? $suspensionReason : null,
        ]);
        $user->syncRoles($role->value);
        $this->record($email, 'created');

        return $user;
    }

    /**
     * @param  list<string>  $cropNames
     * @param  array{type: TawadType, amount: float, min_quantity: float|null}|null  $tawad
     */
    private function listing(
        ?User $seller,
        string $sellerEmail,
        ?Farm $farm,
        string $title,
        array $cropNames,
        ListingUnit $unit,
        float $price,
        float $quantity,
        float $min,
        float $step,
        ?int $availableInDays,
        ?array $tawad,
    ): ?Listing {
        $item = "Listing {$title}";

        if ($seller !== null) {
            $existing = Listing::withTrashed()
                ->where('farmer_seller_id', $seller->id)
                ->where('title', $title)
                ->first();

            if ($existing !== null) {
                $this->record($item, 'exists');
                $live = $existing->trashed() ? null : $existing;
                $this->ensureFixtureHarvest($live);
                $this->tawad($live, $title, $tawad);

                return $live;
            }
        } elseif (! $this->wasPlanned('user:'.$sellerEmail)) {
            $this->record($item, "skipped: seller {$sellerEmail} is missing");

            return null;
        }

        $crop = $this->cropType($farm, $cropNames);

        if ($crop === null) {
            $this->record($item, 'skipped: crop type '.implode(' / ', $cropNames).' does not exist');

            return null;
        }

        if ($seller !== null && ! $seller->mayUseCropType($crop->id)) {
            $this->record($item, "skipped: {$crop->name} is not on the seller's crop list");

            return null;
        }

        $problem = $this->listingProblem($seller, $farm, $crop, $unit, $price, $min, $step);

        if ($problem !== null) {
            $this->record($item, 'skipped: '.$problem);

            return null;
        }

        $tawadProblem = $tawad === null ? null : $this->tawadProblem($farm, $crop, $unit, $price, $tawad);

        if ($this->dryRun || $seller === null || $farm === null) {
            $this->plan('listing:'.$sellerEmail.':'.$title);
            $this->record($item, 'would create');

            if ($tawad !== null) {
                $this->record("Tawad {$title}", $tawadProblem === null ? 'would create' : 'skipped: '.$tawadProblem);
            }

            return null;
        }

        $listing = $this->createListing->handle($seller, [
            'crop_type_id' => $crop->id,
            'unit' => $unit,
            'title' => $title,
            'price_per_unit' => $price,
            'quantity_available' => $quantity,
            'min_order_quantity' => $min,
            'order_step' => $step,
            'is_active' => true,
            'available_from' => $availableInDays === null ? null : now()->addDays($availableInDays),
        ]);
        $this->ensureFixtureHarvest($listing);
        $this->record($item, 'created');
        $this->tawad($listing, $title, $tawad, $tawadProblem);

        return $listing;
    }

    /**
     * Available fixtures start with an Initial record equal to their stock.
     * Upcoming fixtures wait for the actual harvest. Nothing else is invented.
     */
    private function ensureFixtureHarvest(?Listing $listing): void
    {
        if ($listing === null || $this->dryRun) {
            return;
        }

        if ($listing->stock_tracked_since === null) {
            $listing->stock_tracked_since = now();
        }

        if ($listing->isUpcoming()) {
            $listing->needs_actual_harvest = true;
            $listing->save();

            return;
        }

        $listing->needs_actual_harvest = false;
        $listing->save();

        if ($listing->harvestRecords()->exists()) {
            return;
        }

        $quantity = HarvestInput::scale($listing->quantity_available);
        $this->harvestRecords->write($listing, HarvestRecordKind::Initial, [
            'harvested_on' => $listing->harvested_on?->toDateString() ?? now()->toDateString(),
            'quantity_harvested' => $quantity,
            'quantity_rejected' => '0.00',
            'quantity_good' => $quantity,
            'rejection_reason' => null,
            'rejection_note' => null,
            'production_cost' => null,
            'cost_breakdown' => null,
        ], $listing->farmer_seller_id);
    }

    /**
     * @param  array{type: TawadType, amount: float, min_quantity: float|null}|null  $tawad
     */
    private function tawad(?Listing $listing, string $title, ?array $tawad, ?string $knownProblem = null): void
    {
        if ($tawad === null) {
            return;
        }

        $item = "Tawad {$title}";

        if ($knownProblem !== null) {
            $this->record($item, 'skipped: '.$knownProblem);

            return;
        }

        if ($listing === null) {
            $this->record($item, 'skipped: listing is missing');

            return;
        }

        $active = $listing->tawadRules()->where('is_active', true)->first();

        if ($active !== null) {
            $same = $active->type === $tawad['type']
                && (float) $active->discount_amount === $tawad['amount']
                && (float) ($active->min_quantity ?? 0) === (float) ($tawad['min_quantity'] ?? 0);

            $this->record($item, $same ? 'exists' : 'skipped: listing already has a tawad rule');

            return;
        }

        if ($this->dryRun) {
            $this->record($item, 'would create');

            return;
        }

        $listing->tawadRules()->create([
            'type' => $tawad['type'],
            'discount_amount' => $tawad['amount'],
            'min_quantity' => $tawad['min_quantity'],
            'is_active' => true,
        ]);
        $this->record($item, 'created');
    }

    /**
     * @param  list<string>  $names
     */
    private function cropType(?Farm $farm, array $names): ?CropType
    {
        return CropType::query()
            ->forFarm($farm?->id)
            ->where('is_active', true)
            ->where(function (Builder $query) use ($names): void {
                $query->whereIn('name', $names)
                    ->orWhereIn('label_en', $names)
                    ->orWhereIn('label_fil', $names);
            })
            ->first();
    }

    private function listingProblem(?User $seller, ?Farm $farm, CropType $crop, ListingUnit $unit, float $price, float $min, float $step): ?string
    {
        if (! $this->units->accepts($crop, $unit, $farm?->id ?? $seller?->farm_id)) {
            return $this->units->refusalMessage($crop, $farm?->id ?? $seller?->farm_id);
        }

        $validator = Validator::make([], []);
        Listing::addOrderRuleErrors($validator, $unit, $min, $step);

        if ($validator->errors()->isNotEmpty()) {
            return (string) $validator->errors()->first();
        }

        $converted = $this->units->guardPrice($unit, $crop->unit_of_measure, $price);
        $guard = $this->priceGuards->forFarmId($farm?->id ?? $seller?->farm_id, $crop);

        if ($converted === null || ! $guard->allowsPrice($converted)) {
            $floor = number_format($guard->floor, 2, '.', '');

            return "The floor price for {$crop->name} is PHP {$floor} per {$crop->unit_of_measure->value}.";
        }

        return null;
    }

    /**
     * @param  array{type: TawadType, amount: float, min_quantity: float|null}  $tawad
     */
    private function tawadProblem(?Farm $farm, CropType $crop, ListingUnit $unit, float $price, array $tawad): ?string
    {
        $guard = $this->priceGuards->forFarmId($farm?->id, $crop);
        $amount = $this->units->guardPrice($unit, $crop->unit_of_measure, $tawad['amount']);

        if ($amount === null || ! $guard->allowsDiscount($amount)) {
            $max = number_format($guard->ceiling, 2, '.', '');

            return "The maximum tawad for {$crop->name} is PHP {$max}.";
        }

        $probe = new Listing([
            'price_per_unit' => $price,
            'unit' => $unit,
            'crop_type_id' => $crop->id,
        ]);
        $probe->setRelation('cropType', $crop);
        $rule = new TawadRule([
            'type' => $tawad['type'],
            'discount_amount' => $tawad['amount'],
            'min_quantity' => $tawad['min_quantity'],
        ]);

        if (! $rule->keepsUnitPriceAbove($probe, $guard->floor)) {
            $floor = number_format($guard->floor, 2, '.', '');

            return "This tawad would bring the unit price below the floor of PHP {$floor}.";
        }

        return null;
    }

    private function reservation(?User $buyer, string $buyerEmail, ?Listing $listing, string $title, float $quantity): void
    {
        $item = "Reservation {$title} ({$buyerEmail})";

        if ($buyer === null || $listing === null) {
            $planned = ($buyer !== null || $this->wasPlanned('user:'.$buyerEmail))
                && ($listing !== null || $this->listingWasPlanned($title));
            $this->record($item, $planned ? 'would create' : 'skipped: buyer or listing is missing');

            return;
        }

        $exists = Reservation::query()
            ->where('buyer_id', $buyer->id)
            ->where('listing_id', $listing->id)
            ->exists();

        if ($exists) {
            $this->record($item, 'exists');

            return;
        }

        if ($this->dryRun) {
            $this->record($item, 'would create');

            return;
        }

        try {
            $this->reserveListing->handle($buyer, $listing->id, $quantity, FulfillmentPreference::BuyerPickup, null);
            $this->record($item, 'created');
        } catch (ValidationException|HttpException $exception) {
            $this->record($item, 'skipped: '.$this->reason($exception));
        }
    }

    /**
     * @param  array{rating: int, comment: string}|null  $review
     */
    private function order(
        ?User $buyer,
        string $buyerEmail,
        ?User $seller,
        ?Listing $listing,
        string $title,
        float $quantity,
        bool $complete,
        ?array $review,
    ): ?Order {
        $item = "Order {$title} ({$buyerEmail})";

        if ($buyer === null || $seller === null || $listing === null) {
            $planned = $this->wasPlanned('user:'.$buyerEmail) && $this->listingWasPlanned($title);
            $this->record($item, $planned ? 'would create' : 'skipped: buyer, seller, or listing is missing');

            if ($review !== null) {
                $this->record("Review {$title}", $planned ? 'would create' : 'skipped: order is missing');
            }

            return null;
        }

        $existing = Order::query()
            ->where('buyer_id', $buyer->id)
            ->whereHas('items', fn (Builder $query): Builder => $query->where('listing_id', $listing->id))
            ->first();

        if ($existing !== null) {
            $this->record($item, 'exists');
            $this->review($buyer, $existing, $title, $review);

            return $existing;
        }

        if ($this->dryRun) {
            $this->record($item, 'would create');

            if ($review !== null) {
                $this->record("Review {$title}", 'would create');
            }

            return null;
        }

        if ($buyer->cartItems()->exists()) {
            $this->record($item, 'skipped: buyer cart is not empty');

            return null;
        }

        try {
            $this->addToCart($buyer, $listing->id, $quantity);
            $placed = $this->checkout->checkout($buyer, FulfillmentPreference::BuyerPickup)->first();

            if ($placed === null) {
                $this->record($item, 'skipped: checkout did not create an order');

                return null;
            }

            if ($complete) {
                $placed = $this->orders->transition($placed, OrderStatus::Confirmed, $seller);
                $placed = $this->orders->transition($placed, OrderStatus::Ready, $seller);
                $placed = $this->orders->transition($placed, OrderStatus::Completed, $seller, amountReceived: (float) $placed->total);
            }

            $this->record($item, 'created');
            $this->review($buyer, $placed, $title, $review);

            return $placed;
        } catch (ValidationException|HttpException $exception) {
            $this->record($item, 'skipped: '.$this->reason($exception));

            return null;
        }
    }

    /**
     * @param  array{rating: int, comment: string}|null  $review
     */
    private function review(User $buyer, Order $order, string $title, ?array $review): void
    {
        if ($review === null) {
            return;
        }

        $item = "Review {$title}";
        $existing = Review::query()->where('order_id', $order->id)->first();

        if ($existing !== null) {
            $this->record($item, 'exists');

            return;
        }

        if ($order->status !== OrderStatus::Completed) {
            $this->record($item, 'skipped: order is not completed');

            return;
        }

        if ($this->dryRun) {
            $this->record($item, 'would create');

            return;
        }

        if (! $buyer->can('createForOrder', [Review::class, $order])) {
            $this->record($item, 'skipped: this order cannot be reviewed');

            return;
        }

        $saved = Review::query()->create([
            'order_id' => $order->id,
            'buyer_id' => $buyer->id,
            'farmer_seller_id' => $order->farmer_seller_id,
            'rating' => $review['rating'],
            'comment' => $review['comment'],
        ]);
        $order->setRelation('review', $saved);
        $this->record($item, 'created');
    }

    private function reportListing(?User $buyer, string $buyerEmail, ?Listing $listing, string $title): void
    {
        $item = "Report listing {$title}";

        if ($buyer === null || $listing === null) {
            $this->record($item, $this->wasPlanned('user:'.$buyerEmail) && $this->listingWasPlanned($title)
                ? 'would create'
                : 'skipped: buyer or listing is missing');

            return;
        }

        $this->submitReport($buyer, $listing, 'listing', $listing->id, ReportReason::WrongOrMisleading, 'Photo does not match', $item);
    }

    private function reportReview(?User $buyer, string $buyerEmail, ?Order $order): void
    {
        $item = 'Report review Okra Sariwa';
        $review = $order === null
            ? null
            : Review::query()->where('order_id', $order->id)->first();

        if ($buyer === null || $review === null) {
            $planned = $this->dryRun
                && ($buyer !== null || $this->wasPlanned('user:'.$buyerEmail))
                && ($review !== null || $this->listingWasPlanned('Okra Sariwa'));
            $this->record($item, $planned ? 'would create' : 'skipped: buyer or review is missing');

            return;
        }

        $this->submitReport($buyer, $review, 'review', $review->id, ReportReason::OffensiveContent, null, $item);
    }

    private function submitReport(User $buyer, Listing|Review $target, string $type, int $id, ReportReason $reason, ?string $details, string $item): void
    {
        $open = Report::query()
            ->where('reporter_id', $buyer->id)
            ->where('reportable_type', $target->getMorphClass())
            ->where('reportable_id', $target->getKey())
            ->where('status', ReportStatus::Open)
            ->exists();

        if ($open) {
            $this->record($item, 'exists');

            return;
        }

        if ($this->dryRun) {
            $this->record($item, 'would create');

            return;
        }

        try {
            $this->reports->handle($buyer, $type, $id, $reason, $details);
            $this->record($item, 'created');
        } catch (ValidationException $exception) {
            $this->record($item, 'skipped: '.$this->reason($exception));
        }
    }

    private function deletionRequest(?User $user, string $email): void
    {
        $item = "Deletion request {$email}";

        if ($user === null) {
            $this->record($item, $this->wasPlanned('user:'.$email) ? 'would create' : 'skipped: buyer is missing');

            return;
        }

        $pending = $user->accountDeletionRequests()
            ->where('status', AccountDeletionStatus::Pending)
            ->exists();

        if ($pending) {
            $this->record($item, 'exists');

            return;
        }

        if ($this->dryRun) {
            $this->record($item, 'would create');

            return;
        }

        try {
            $this->deletionRequests->handle($user, 'Test account');
            $this->record($item, 'created');
        } catch (ValidationException $exception) {
            $this->record($item, 'skipped: '.$this->reason($exception));
        }
    }

    private function announcement(?Farm $farm, ?User $editor): void
    {
        $item = 'Announcement Saturday harvest is on — come early';
        $title = 'Saturday harvest is on — come early';

        if ($farm === null) {
            $this->record($item, $this->wasPlanned('farm:pyap-manggahan-chapter') ? 'would create' : 'skipped: PYAP farm is missing');

            return;
        }

        $existing = FarmAnnouncement::query()
            ->where('farm_id', $farm->id)
            ->where('title', $title)
            ->first();

        if ($existing !== null) {
            $this->record($item, 'exists');

            return;
        }

        $author = $editor ?? $farm->contentEditor()->first();

        if ($author === null) {
            $this->record($item, $this->wasPlanned('user:editor01@gmail.com') ? 'would create' : 'skipped: PYAP has no Content Editor to author it');

            return;
        }

        if ($this->dryRun) {
            $this->record($item, 'would create');

            return;
        }

        FarmAnnouncement::query()->create([
            'farm_id' => $farm->id,
            'author_id' => $author->id,
            'title' => $title,
            'body' => 'Saturday harvest is on. Come early.',
            'audience' => AnnouncementAudience::Public,
            'starts_at' => now(),
            'is_pinned' => true,
        ]);
        $this->record($item, 'created');
    }

    private function addToCart(User $buyer, int $listingId, float $quantity): void
    {
        $request = StoreCartItemRequest::create('/api/cart', 'POST', [
            'listing_id' => $listingId,
            'quantity' => $quantity,
        ]);
        $request->headers->set('Accept', 'application/json');
        $request->setUserResolver(fn (): User => $buyer);
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->validateResolved();

        app(CartController::class)->store($request);
    }

    private function listingWasPlanned(string $title): bool
    {
        foreach ($this->planned as $key => $unused) {
            if (str_ends_with($key, ':'.$title)) {
                return true;
            }
        }

        return false;
    }

    private function plan(string $key): void
    {
        $this->planned[$key] = true;
    }

    private function wasPlanned(string $key): bool
    {
        return isset($this->planned[$key]);
    }

    private function record(string $item, string $status): void
    {
        $this->rows[] = ['item' => $item, 'status' => $status];
    }

    private function reason(ValidationException|HttpException $exception): string
    {
        if ($exception instanceof ValidationException) {
            $message = collect($exception->errors())->flatten()->first();

            return is_string($message) ? $message : $exception->getMessage();
        }

        return $exception->getMessage();
    }
}
