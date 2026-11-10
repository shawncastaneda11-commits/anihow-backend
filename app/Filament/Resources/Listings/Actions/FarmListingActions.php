<?php

namespace App\Filament\Resources\Listings\Actions;

use App\Actions\Listings\EditFarmListing;
use App\Enums\GrowingMethod;
use App\Enums\HarvestRejectionReason;
use App\Enums\ListingStatus;
use App\Enums\ListingUnit;
use App\Enums\ProductCategory;
use App\Enums\StockRemovalReason;
use App\Enums\TawadType;
use App\Models\Listing;
use App\Models\TawadRule;
use App\Models\User;
use App\Support\Pricing\PriceGuardResolver;
use App\Support\Pricing\UnitConverter;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * A Content Editor's edits to their farm's listings, in the panel beside the
 * list (ListListings):
 * price and stock, tawad, details and dates. Everything the seller can change
 * in the app except photos. Each change goes through EditFarmListing, so the
 * app's own checks apply and the seller is told.
 */
class FarmListingActions
{
    public const NO_TAWAD = 'none';

    /** Fields the listing itself takes; the rest of the form is the tawad. */
    private const LISTING_FIELDS = [
        'title', 'description', 'unit', 'price_per_unit', 'min_order_quantity',
        'order_step', 'growing_method', 'available_from', 'available_until', 'harvested_on', 'is_active',
    ];

    public static function canEdit(Listing $record): bool
    {
        return $record->status !== ListingStatus::TakenDown
            && $record->farmer_seller_id !== auth()->id()
            && (auth()->user()?->can('update', $record) ?? false)
            && (auth()->user()?->farm_id !== null);
    }

    /**
     * The panel's starting values for a listing.
     *
     * @return array<string, mixed>
     */
    public static function formData(Listing $record): array
    {
        return [
            'title' => $record->title,
            'description' => $record->description,
            'unit' => $record->unit?->value,
            'price_per_unit' => $record->price_per_unit,
            'min_order_quantity' => $record->min_order_quantity,
            'order_step' => $record->order_step,
            'is_active' => (bool) $record->is_active,
            'available_from' => $record->available_from?->toDateTimeString(),
            'available_until' => $record->available_until?->toDateTimeString(),
            'harvested_on' => $record->harvested_on?->toDateString(),
            'growing_method' => $record->growing_method?->value,
            'type' => $record->activeTawadRule?->type?->value ?? self::NO_TAWAD,
            'discount_amount' => $record->activeTawadRule?->discount_amount,
            'min_quantity' => $record->activeTawadRule?->min_quantity,
            'confirm_cancel_reservations' => false,
        ];
    }

    /**
     * The panel: price and stock, tawad, details and dates, then what the
     * seller will be told.
     *
     * @return list<mixed>
     */
    public static function panelSchema(): array
    {
        return [
            Tabs::make('sections')->tabs([
                Tab::make('Price and stock')->schema(self::priceAndStock()),
                Tab::make('Tawad')->schema(self::tawad()),
                Tab::make('Details')->schema([
                    TextInput::make('title')->required()->maxLength(150),
                    Textarea::make('description')->rows(4)->maxLength(5000),
                    Select::make('growing_method')->label('How it was grown')->options(GrowingMethod::options())->native(false),
                ]),
                Tab::make('Dates')->schema([
                    DatePicker::make('harvested_on')->label('Harvested on'),
                    Grid::make(2)->schema([
                        DateTimePicker::make('available_from')->label('Available from')->seconds(false)->live(),
                        DateTimePicker::make('available_until')->label('Until')->seconds(false),
                    ]),
                    Text::make(fn (Get $get): string => self::startNote($get))
                        ->visible(fn (Get $get): bool => self::startNote($get) !== ''),
                    Text::make('After "until", the listing stops showing to buyers and its stock counts as left over.')->color('gray'),
                ]),
            ])->contained(false),
            Text::make(fn (Get $get, Listing $record): string => self::changeSummary($record, $get))
                ->color('gray'),
        ];
    }

    /**
     * Saves through save() and shows a refusal under the field it is about.
     *
     * @param  array<string, mixed>  $data
     */
    public static function saveFor(string $statePath, Listing $record, array $data): void
    {
        try {
            self::save(self::editor(), $record, $data);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(
                collect($exception->errors())
                    ->mapWithKeys(fn (array $messages, string $field): array => ["{$statePath}.{$field}" => $messages])
                    ->all(),
            );
        }
    }

    /**
     * @return list<mixed>
     */
    private static function priceAndStock(): array
    {
        return [
            Grid::make(['default' => 1, 'sm' => 2])->schema([
                TextInput::make('price_per_unit')
                    ->label('Price per unit')
                    ->numeric()
                    ->required()
                    ->prefix('₱')
                    ->live(debounce: 400),
                Select::make('unit')->options(ListingUnit::options())->required()->native(false)->live(),
            ]),
            Text::make(fn (Get $get, Listing $record): string => self::floorHint($record, $get)['text'])
                ->color(fn (Get $get, Listing $record): string => self::floorHint($record, $get)['below'] ? 'danger' : 'gray'),
            Grid::make(['default' => 1, 'sm' => 2])->schema([
                TextInput::make('min_order_quantity')->label('Minimum order')->numeric()->required(),
                TextInput::make('order_step')->label('Sold in steps of')->numeric()->required(),
            ]),
            Text::make(fn (Listing $record): string => self::stockText($record)),
            Actions::make([self::addStockAction(), self::removeStockAction()]),
            Text::make(fn (Listing $record): string => self::reservationText($record))->color('gray'),
            Toggle::make('is_active')
                ->label('Shown to buyers')
                ->helperText('Hiding keeps the listing for the seller to bring back.')
                ->live(),
            Checkbox::make('confirm_cancel_reservations')
                ->label('Cancel the reservations this change affects')
                ->visible(fn (Listing $record): bool => (float) $record->activeReservedQuantity() > 0),
        ];
    }

    /**
     * Stock is changed the way the app changes it: a harvest added, or stock
     * taken off with a reason, never typed over.
     */
    public static function stockText(Listing $record): string
    {
        $unit = $record->unit?->value ?? '';
        $text = 'Stock: '.self::number($record->quantity_available)." {$unit}";

        if ((float) $record->quantity_held > 0) {
            $text .= ', '.self::number($record->quantity_held).' of it held by orders not yet confirmed';
        }

        return $text.'. Add or remove stock below; the seller is told.';
    }

    private static function addStockAction(): Action
    {
        return Action::make('addStock')
            ->label('Add stock')
            ->icon('heroicon-o-plus')
            ->color('gray')
            ->modalHeading('Add stock')
            ->modalDescription('Records a harvest for the seller, as Add stock does in the app.')
            ->schema([
                TextInput::make('quantity_harvested')->label('Quantity harvested')->numeric()->required(),
                TextInput::make('quantity_rejected')->label('Not good enough to sell')->numeric()->default(0)->live(debounce: 400),
                Select::make('rejection_reason')
                    ->label('Why')
                    ->options(fn (Listing $record): array => self::rejectionReasons($record))
                    ->native(false)
                    ->live()
                    ->visible(fn (Get $get): bool => (float) $get('quantity_rejected') > 0)
                    ->required(fn (Get $get): bool => (float) $get('quantity_rejected') > 0),
                TextInput::make('rejection_note')
                    ->label('What happened')
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => $get('rejection_reason') === HarvestRejectionReason::Other->value)
                    ->required(fn (Get $get): bool => $get('rejection_reason') === HarvestRejectionReason::Other->value),
                DatePicker::make('harvested_on')->label('Harvested on')->default(now())->maxDate(now())->required(),
            ])
            ->action(function (array $data, Listing $record, $livewire): void {
                abort_unless(self::canEdit($record), 403);
                app(EditFarmListing::class)->addStock(self::editor(), $record, $data);
                Notification::make()->title('Stock added. The seller has been told.')->success()->send();
                $livewire->openListing($record->getKey());
            });
    }

    /**
     * The reasons the app offers for this crop: fresh produce or value-added.
     *
     * @return array<string, string>
     */
    private static function rejectionReasons(Listing $record): array
    {
        $record->loadMissing('cropType');
        $valueAdded = $record->cropType?->category === ProductCategory::ValueAdded;

        return collect($valueAdded ? HarvestRejectionReason::forValueAdded() : HarvestRejectionReason::forFresh())
            ->mapWithKeys(fn (HarvestRejectionReason $reason): array => [$reason->value => $reason->label()])
            ->all();
    }

    private static function removeStockAction(): Action
    {
        return Action::make('removeStock')
            ->label('Remove stock')
            ->icon('heroicon-o-minus')
            ->color('gray')
            ->modalHeading('Remove stock')
            ->modalDescription('Stock held by orders not yet confirmed cannot be removed.')
            ->schema([
                TextInput::make('quantity')->label('Quantity to remove')->numeric()->required(),
                Select::make('reason')->options(StockRemovalReason::options())->required()->native(false)->live(),
                TextInput::make('note')
                    ->label('What happened')
                    ->maxLength(255)
                    ->required(fn (Get $get): bool => $get('reason') === StockRemovalReason::Correction->value),
            ])
            ->action(function (array $data, Listing $record, $livewire): void {
                abort_unless(self::canEdit($record), 403);
                app(EditFarmListing::class)->removeStock(self::editor(), $record, $data);
                Notification::make()->title('Stock removed. The seller has been told.')->success()->send();
                $livewire->openListing($record->getKey());
            });
    }

    /**
     * @return list<mixed>
     */
    private static function tawad(): array
    {
        return [
            Radio::make('type')
                ->label('Who gets the tawad')
                ->options([
                    self::NO_TAWAD => 'No tawad',
                    TawadType::Flat->value => 'Every order',
                    TawadType::MinimumQuantity->value => 'Buying more',
                ])
                ->descriptions([
                    self::NO_TAWAD => 'Saving ends the current tawad, if there is one.',
                    TawadType::Flat->value => 'Pesos off any order, whatever the quantity.',
                    TawadType::MinimumQuantity->value => 'Only when they buy at least a set amount.',
                ])
                ->required()
                ->live(),
            Grid::make(2)
                ->visible(fn (Get $get): bool => $get('type') !== self::NO_TAWAD)
                ->schema([
                    TextInput::make('discount_amount')
                        ->label('Peso off the order')
                        ->numeric()
                        ->prefix('₱')
                        ->live(debounce: 400)
                        ->required(fn (Get $get): bool => $get('type') !== self::NO_TAWAD),
                    TextInput::make('min_quantity')
                        ->label('When they buy at least')
                        ->numeric()
                        ->live(debounce: 400)
                        ->visible(fn (Get $get): bool => $get('type') === TawadType::MinimumQuantity->value)
                        ->required(fn (Get $get): bool => $get('type') === TawadType::MinimumQuantity->value),
                ]),
            // The same check the save runs, as you type: too big, or under
            // the lowest price once taken off.
            Text::make(fn (Get $get, Listing $record): string => self::tawadCheck($record, $get)['text'])
                ->color(fn (Get $get, Listing $record): string => self::tawadCheck($record, $get)['problem'] ? 'danger' : 'gray')
                ->visible(fn (Get $get): bool => $get('type') !== self::NO_TAWAD),
            Text::make(fn (Get $get, Listing $record): string => self::buyerPays($record, $get))
                ->visible(fn (Get $get): bool => $get('type') !== self::NO_TAWAD),
        ];
    }

    /**
     * The tawad as typed, judged as the app judges it (TawadRule::guardProblem):
     * spread over the quantity it applies to, then held to the biggest tawad
     * and the lowest price for this crop.
     *
     * @return array{text: string, problem: bool}
     */
    public static function tawadCheck(Listing $record, Get $get): array
    {
        try {
            $type = TawadType::tryFrom((string) $get('type'));
            $off = $get('discount_amount');
            $cropType = $record->cropType;

            if ($type === null || $cropType === null) {
                return ['text' => '', 'problem' => false];
            }

            $probe = self::probe($record, $get);
            $guard = app(PriceGuardResolver::class)->forFarmId($probe->farm_id, $cropType, $probe->unit);
            $guardUnit = ($guard->unit ?? $cropType->unit_of_measure)?->value ?? 'unit';
            $rule = new TawadRule([
                'type' => $type,
                'discount_amount' => is_numeric($off) ? (float) $off : 0.0,
                'min_quantity' => $type === TawadType::MinimumQuantity && is_numeric($get('min_quantity')) ? (float) $get('min_quantity') : null,
            ]);

            $limit = $guard->hasCeiling() && (float) $guard->ceiling > 0
                ? "The most tawad for {$cropType->name} is ₱{$guard->ceilingText()} per {$guardUnit}, counted over ".($type === TawadType::MinimumQuantity ? 'the quantity they buy at least' : 'the smallest order').'.'
                : '';

            if (! is_numeric($off) || (float) $off <= 0) {
                return ['text' => $limit, 'problem' => false];
            }

            $perUnit = $rule->perUnitInCropUnit($probe);

            return match ($rule->guardProblem($probe, $guard)) {
                'ceiling' => [
                    'text' => $guard->hasCeiling() && (float) $guard->ceiling > 0
                        ? 'Too big. This works out to ₱'.number_format((float) $perUnit, 2)." per {$guardUnit}; the most for {$cropType->name} is ₱{$guard->ceilingText()}. Lower it to save."
                        : "Tawad is turned off for {$cropType->name}.",
                    'problem' => true,
                ],
                'floor' => [
                    'text' => 'This brings the price under the lowest price of ₱'.number_format($guard->floor, 2)." per {$guardUnit}. Lower it to save.",
                    'problem' => true,
                ],
                'unit' => ['text' => 'This unit cannot be checked against the price limits. Change the unit to save.', 'problem' => true],
                default => ['text' => 'Works out to ₱'.number_format((float) $perUnit, 2)." off per {$guardUnit}. {$limit}", 'problem' => false],
            };
        } catch (Throwable) {
            return ['text' => '', 'problem' => false];
        }
    }

    /**
     * The listing with the price, unit and minimum order as typed, unsaved.
     */
    private static function probe(Listing $record, Get $get): Listing
    {
        $probe = clone $record;

        if (is_numeric($get('price_per_unit'))) {
            $probe->price_per_unit = (float) $get('price_per_unit');
        }

        if (($unit = ListingUnit::tryFrom((string) $get('unit'))) !== null) {
            $probe->unit = $unit;
        }

        if (is_numeric($get('min_order_quantity'))) {
            $probe->min_order_quantity = (float) $get('min_order_quantity');
        }

        return $probe;
    }

    /**
     * When the listing opens, if "Available from" is later than now.
     */
    public static function startNote(Get $get): string
    {
        try {
            $from = $get('available_from');
            $start = filled($from) ? Carbon::parse((string) $from) : null;
        } catch (Throwable) {
            return '';
        }

        if ($start === null || ! $start->isFuture()) {
            return '';
        }

        return 'Starts '.$start->format('M j, g:i A').'. Buyers see it as coming soon and cannot order before then. '
            .'If the seller takes online payment and the farm allows reservations, buyers can reserve until an hour before it opens; '
            .'reservations become orders when it opens. Orders already placed are not changed.';
    }

    /**
     * Saves the listing and its tawad together: if the tawad is refused, the
     * listing change is undone too.
     *
     * @param  array<string, mixed>  $data
     */
    public static function save(User $editor, Listing $record, array $data): void
    {
        $confirm = (bool) ($data['confirm_cancel_reservations'] ?? false);
        $listingData = array_intersect_key($data, array_flip(self::LISTING_FIELDS));
        $type = (string) ($data['type'] ?? self::NO_TAWAD);
        $edit = app(EditFarmListing::class);

        DB::transaction(function () use ($edit, $editor, $record, $listingData, $confirm, $type, $data): void {
            $listing = $edit->update($editor, $record, $listingData, $confirm);
            $current = $listing->activeTawadRule()->first();

            if ($type === self::NO_TAWAD) {
                if ($current !== null) {
                    $edit->endTawad($editor, $listing);
                }

                return;
            }

            $wanted = [
                'type' => $type,
                'discount_amount' => $data['discount_amount'] ?? null,
                'min_quantity' => $type === TawadType::MinimumQuantity->value ? ($data['min_quantity'] ?? null) : null,
            ];

            $unchanged = $current !== null
                && $current->type?->value === $type
                && (float) $current->discount_amount === (float) $wanted['discount_amount']
                && (float) $current->min_quantity === (float) $wanted['min_quantity'];

            if (! $unchanged) {
                $edit->setTawad($editor, $listing, array_filter($wanted, fn (mixed $value): bool => $value !== null));
            }
        });
    }

    /**
     * The lowest price for the unit chosen, and whether the typed price is
     * under it. The save runs the full check either way.
     *
     * @return array{text: string, below: bool}
     */
    public static function floorHint(Listing $record, Get $get): array
    {
        try {
            $unit = ListingUnit::tryFrom((string) $get('unit')) ?? $record->unit;
            $guardUnit = $record->cropType?->guardUnitFor($unit);

            if ($record->cropType === null || $guardUnit === null) {
                return ['text' => '', 'below' => false];
            }

            $resolver = app(PriceGuardResolver::class);
            $guard = $record->farm !== null
                ? $resolver->for($record->farm, $record->cropType, $unit)
                : $resolver->system($record->cropType, $unit);

            if ($guard->floor <= 0) {
                return ['text' => 'No lowest price for this crop.', 'below' => false];
            }

            $floor = '₱'.number_format($guard->floor, 2).' / '.$guardUnit->value;
            $price = $get('price_per_unit');
            $converted = is_numeric($price) ? app(UnitConverter::class)->guardPrice($unit, $guardUnit, (float) $price) : null;
            $below = $converted !== null && ! $guard->allowsPrice($converted);

            return [
                'text' => $below
                    ? "Below the lowest price of {$floor}. Raise it to save."
                    : "Lowest price {$floor}. Orders already placed keep their price.",
                'below' => $below,
            ];
        } catch (Throwable) {
            return ['text' => '', 'below' => false];
        }
    }

    /**
     * What a buyer pays at a few quantities with the tawad as typed. The
     * tawad is pesos off the whole order, once it applies.
     */
    public static function buyerPays(Listing $record, Get $get): string
    {
        $price = is_numeric($get('price_per_unit')) ? (float) $get('price_per_unit') : (float) $record->price_per_unit;
        $off = is_numeric($get('discount_amount')) ? (float) $get('discount_amount') : 0.0;
        $smallest = is_numeric($get('min_order_quantity')) ? max(0.01, (float) $get('min_order_quantity')) : 1.0;
        $threshold = $get('type') === TawadType::MinimumQuantity->value && is_numeric($get('min_quantity'))
            ? max($smallest, (float) $get('min_quantity'))
            : $smallest;
        $unit = ListingUnit::tryFrom((string) $get('unit'))?->value ?? $record->unit?->value ?? 'unit';

        $quantities = array_values(array_unique([$smallest, $threshold, $threshold + 2]));

        return 'What the buyer pays: '.collect($quantities)
            ->map(function (float $quantity) use ($price, $off, $threshold, $unit): string {
                $tawad = $off > 0 && $quantity >= $threshold;
                $total = max(0, $quantity * $price - ($tawad ? $off : 0));

                return self::number($quantity)." {$unit} ₱".number_format($total, 2).($tawad ? ' with tawad' : '');
            })
            ->implode(' · ');
    }

    /**
     * The sentence above the save button: what the seller will be told,
     * comparing the panel with the values it opened with (formData()).
     */
    public static function changeSummary(Listing $record, Get $get): string
    {
        $seller = $record->farmerSeller?->name ?? 'The seller';
        $before = self::formData($record);
        $changed = fn (string $field): bool => self::differs($field, $before[$field] ?? null, $get($field));
        $changes = [];

        if ($changed('price_per_unit') && is_numeric($get('price_per_unit'))) {
            $changes[] = 'price ₱'.number_format((float) $record->price_per_unit, 2).' → ₱'.number_format((float) $get('price_per_unit'), 2);
        }

        if ($changed('is_active')) {
            $changes[] = $get('is_active') ? 'shown to buyers again' : 'hidden from buyers';
        }

        foreach (['title' => 'title', 'description' => 'description', 'unit' => 'unit', 'min_order_quantity' => 'minimum order', 'order_step' => 'order step', 'growing_method' => 'how it was grown', 'harvested_on' => 'harvest date', 'available_from' => 'start date', 'available_until' => 'end date'] as $field => $label) {
            if ($changed($field)) {
                $changes[] = $label;
            }
        }

        $type = (string) $get('type');
        $tawadChanged = $changed('type') || ($type !== self::NO_TAWAD && ($changed('discount_amount')
            || ($type === TawadType::MinimumQuantity->value && $changed('min_quantity'))));

        if ($tawadChanged) {
            $changes[] = $type === self::NO_TAWAD ? 'tawad ended' : 'new tawad';
        }

        return $changes === []
            ? 'Nothing changed yet. The seller is told only what you change.'
            : "{$seller} will be told: ".implode('; ', $changes).'.';
    }

    /**
     * Whether a panel value differs from where it started: numbers by
     * value, dates by the minute, and blank as blank however it is written.
     */
    private static function differs(string $field, mixed $before, mixed $after): bool
    {
        $blank = fn (mixed $value): bool => $value === null || $value === '' || $value === [];

        if ($blank($before) || $blank($after)) {
            return $blank($before) !== $blank($after);
        }

        if (is_bool($before) || is_bool($after)) {
            return (bool) $before !== (bool) $after;
        }

        if (in_array($field, ['available_from', 'available_until', 'harvested_on'], true)) {
            try {
                $format = $field === 'harvested_on' ? 'Y-m-d' : 'Y-m-d H:i';

                return Carbon::parse((string) $before)->format($format) !== Carbon::parse((string) $after)->format($format);
            } catch (Throwable) {
                return (string) $before !== (string) $after;
            }
        }

        if (is_numeric($before) && is_numeric($after)) {
            return abs((float) $before - (float) $after) > 0.00001;
        }

        return trim((string) $before) !== trim((string) $after);
    }

    private static function reservationText(Listing $record): string
    {
        $reserved = (float) $record->activeReservedQuantity();

        if ($reserved <= 0) {
            return 'No reservations.';
        }

        $unit = $record->unit?->value ?? '';

        return self::number($reserved)." {$unit} reserved. Hiding the listing or going below that stock cancels reservations; tick the box below to allow it.";
    }

    private static function number(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
    }

    private static function editor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
