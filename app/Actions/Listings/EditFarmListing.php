<?php

namespace App\Actions\Listings;

use App\Enums\NotificationType;
use App\Enums\StockRemovalReason;
use App\Http\Requests\Api\Listings\StoreHarvestRequest;
use App\Http\Requests\Api\Listings\StoreStockRemovalRequest;
use App\Http\Requests\Api\Listings\StoreTawadRuleRequest;
use App\Http\Requests\Api\Listings\UpdateListingRequest;
use App\Models\Farm;
use App\Models\Listing;
use App\Models\TawadRule;
use App\Models\User;
use App\Support\HarvestInput;
use App\Support\InAppNotifier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Validation\ValidationException;

/**
 * A farm's Content Editor changing one of the farm's listings from the CMS.
 *
 * The edit goes through the very requests the app uses (UpdateListingRequest,
 * StoreTawadRuleRequest), so the floor price, units, order amounts and tawad
 * limits are checked exactly as for the seller. The seller is told what
 * changed, and the change is in the activity log. Orders already placed keep
 * their price.
 */
class EditFarmListing
{
    public function __construct(
        private readonly UpdateListingAction $updateListing,
        private readonly InAppNotifier $notifier,
        private readonly AddStockAction $addStock,
        private readonly RemoveStockAction $removeStock,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $editor, Listing $listing, array $data, bool $confirmCancelReservations = false): Listing
    {
        /** @var UpdateListingRequest $request */
        $request = $this->resolve(UpdateListingRequest::class, $editor, $listing, $data);

        $before = $listing->only(array_keys($request->listingAttributes()));
        $updated = $this->updateListing->handle($listing, $request->listingAttributes(), null, $confirmCancelReservations);

        $changed = collect($request->listingAttributes())
            ->keys()
            ->filter(fn (string $field): bool => (string) $this->plain($before[$field] ?? null) !== (string) $this->plain($updated->getAttribute($field)))
            ->map(fn (string $field): string => str_replace('_', ' ', $field))
            ->values();

        if ($changed->isNotEmpty()) {
            $this->tellSeller($updated, 'changed '.$changed->implode(', '));
        }

        return $updated;
    }

    /**
     * Replaces the listing's tawad, ending the active one, as the app does.
     *
     * @param  array{type: string, discount_amount: mixed, min_quantity?: mixed}  $data
     */
    public function setTawad(User $editor, Listing $listing, array $data): TawadRule
    {
        $listing->loadMissing('farm');

        if ($listing->farm !== null && ! $listing->farm->allowsTawad()) {
            throw ValidationException::withMessages(['discount_amount' => Farm::TAWAD_OFF_MESSAGE]);
        }

        /** @var StoreTawadRuleRequest $request */
        $request = $this->resolve(StoreTawadRuleRequest::class, $editor, $listing, $data);

        $listing->tawadRules()->where('is_active', true)->update(['is_active' => false, 'ended_at' => now()]);
        $rule = $listing->tawadRules()->create($request->ruleAttributes());

        $this->tellSeller($listing, 'set a new tawad');

        return $rule;
    }

    /**
     * Records a harvest for the seller, as Add stock does in the app.
     *
     * @param  array<string, mixed>  $data
     */
    public function addStock(User $editor, Listing $listing, array $data): Listing
    {
        /** @var StoreHarvestRequest $request */
        $request = $this->resolve(StoreHarvestRequest::class, $editor, $listing, $data);

        $harvest = $request->harvest();
        $updated = $this->addStock->handle($listing, $harvest, $editor);
        $unit = $updated->unit?->value ?? '';

        $this->tellSeller($updated, 'added '.HarvestInput::scale($harvest['quantity_good'] ?? $request->validated('quantity_harvested'))." {$unit} of stock");

        return $updated;
    }

    /**
     * Takes stock off, as Remove stock does in the app. Stock held by orders
     * can't be removed.
     *
     * @param  array<string, mixed>  $data
     */
    public function removeStock(User $editor, Listing $listing, array $data): Listing
    {
        /** @var StoreStockRemovalRequest $request */
        $request = $this->resolve(StoreStockRemovalRequest::class, $editor, $listing, $data);
        $reason = StockRemovalReason::from($request->validated('reason'));

        $updated = $this->removeStock->handle(
            $listing,
            (string) $request->validated('quantity'),
            $reason,
            $request->validated('note'),
            $editor,
        );
        $unit = $updated->unit?->value ?? '';

        $this->tellSeller($updated, 'removed '.HarvestInput::scale($request->validated('quantity'))." {$unit} of stock ({$reason->label()})");

        return $updated;
    }

    public function endTawad(User $editor, Listing $listing): void
    {
        abort_unless($editor->can('update', $listing), 403);

        $ended = $listing->tawadRules()->where('is_active', true)->update(['is_active' => false, 'ended_at' => now()]);

        if ($ended > 0) {
            $this->tellSeller($listing, 'ended the tawad');
        }
    }

    /**
     * Builds and validates one of the app's form requests as if the editor
     * had sent it for this listing. Throws ValidationException with the same
     * messages the seller would see.
     *
     * @template T of FormRequest
     *
     * @param  class-string<T>  $class
     * @param  array<string, mixed>  $data
     * @return T
     */
    private function resolve(string $class, User $editor, Listing $listing, array $data): FormRequest
    {
        $base = Request::create('/api/farmer/listings/'.$listing->getKey(), 'PATCH', $data);
        $request = $class::createFrom($base);
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $editor);

        $route = (new Route(['PATCH'], 'api/farmer/listings/{listing}', []))->bind($request);
        $route->setParameter('listing', $listing);
        $request->setRouteResolver(fn () => $route);

        $request->validateResolved();

        return $request;
    }

    private function tellSeller(Listing $listing, string $what): void
    {
        $listing->loadMissing(['farmerSeller', 'farm']);
        $seller = $listing->farmerSeller;

        if ($seller === null) {
            return;
        }

        $farm = $listing->farm?->name ?? 'your farm';

        $this->notifier->send(
            $seller,
            NotificationType::ListingEditedByFarm,
            NotificationType::ListingEditedByFarm->label(),
            "{$farm}'s Content Editor {$what} on {$listing->title}. Orders already placed keep their price.",
            $listing,
        );
    }

    private function plain(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return is_numeric($value) ? (string) (float) $value : $value;
    }
}
