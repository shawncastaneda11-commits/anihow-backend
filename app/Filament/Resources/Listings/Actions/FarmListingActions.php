<?php

namespace App\Filament\Resources\Listings\Actions;

use App\Actions\Listings\EditFarmListing;
use App\Enums\GrowingMethod;
use App\Enums\ListingStatus;
use App\Enums\ListingUnit;
use App\Enums\TawadType;
use App\Models\Listing;
use App\Models\User;
use App\Support\Pricing\PriceGuardResolver;
use App\Support\Pricing\UnitConverter;
use Closure;
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
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * A Content Editor's edits to their farm's listings, in one side panel:
 * price and stock, tawad, details and dates. Everything the seller can change
 * in the app except photos. Each change goes through EditFarmListing, so the
 * app's own checks apply and the seller is told.
 */
class FarmListingActions
{
    public const NO_TAWAD = 'none';

    /** Fields the listing itself takes; the rest of the form is the tawad. */
    private const LISTING_FIELDS = [
        'title', 'description', 'unit', 'price_per_unit', 'quantity_available', 'min_order_quantity',
        'order_step', 'growing_method', 'available_from', 'available_until', 'harvested_on', 'is_active',
    ];

    /**
     * @return list<Action>
     */
    public static function make(): array
    {
        return [self::edit()];
    }

    public static function canEdit(Listing $record): bool
    {
        return $record->status !== ListingStatus::TakenDown
            && $record->farmer_seller_id !== auth()->id()
            && (auth()->user()?->can('update', $record) ?? false)
            && (auth()->user()?->farm_id !== null);
    }

    private static function edit(): Action
    {
        return Action::make('editFarmListing')
            ->label('Edit')
            ->icon('heroicon-o-pencil-square')
            ->slideOver()
            ->modalWidth(Width::Large)
            ->visible(fn (Listing $record): bool => self::canEdit($record))
            ->modalHeading(fn (Listing $record): string => $record->title)
            ->modalDescription(fn (Listing $record): string => collect([
                $record->farmerSeller?->name,
                $record->cropType?->name,
                $record->created_at ? 'listed '.$record->created_at->format('M j') : null,
            ])->filter()->implode(' · '))
            ->modalSubmitActionLabel('Save and tell the seller')
            ->fillForm(fn (Listing $record): array => [
                'title' => $record->title,
                'description' => $record->description,
                'unit' => $record->unit?->value,
                'price_per_unit' => $record->price_per_unit,
                'quantity_available' => $record->quantity_available,
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
            ])
            ->schema([
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
                            DateTimePicker::make('available_from')->label('Available from')->seconds(false),
                            DateTimePicker::make('available_until')->label('Until')->seconds(false),
                        ]),
                        Text::make('After "until", the listing stops showing to buyers and its stock counts as left over.')->color('gray'),
                    ]),
                ]),
                Text::make(fn (Get $get, Listing $record): string => self::changeSummary($record, $get))
                    ->color('gray'),
            ])
            ->action(function (Listing $record, array $data, Action $action): void {
                self::inModal($action, fn () => self::save(self::editor(), $record, $data));

                Notification::make()->title('Saved. The seller has been told.')->success()->send();
            });
    }

    /**
     * @return list<mixed>
     */
    private static function priceAndStock(): array
    {
        return [
            Grid::make(['default' => 1, 'sm' => 3])->schema([
                TextInput::make('price_per_unit')
                    ->label('Price per unit')
                    ->numeric()
                    ->required()
                    ->prefix('₱')
                    ->live(debounce: 400)
                    ->columnSpan(['sm' => 2]),
                Select::make('unit')->options(ListingUnit::options())->required()->native(false)->live(),
            ]),
            Text::make(fn (Get $get, Listing $record): string => self::floorHint($record, $get)['text'])
                ->color(fn (Get $get, Listing $record): string => self::floorHint($record, $get)['below'] ? 'danger' : 'gray'),
            Grid::make(['default' => 1, 'sm' => 3])->schema([
                TextInput::make('quantity_available')->label('Stock')->numeric()->required(),
                TextInput::make('min_order_quantity')->label('Minimum order')->numeric()->required(),
                TextInput::make('order_step')->label('Sold in steps of')->numeric()->required(),
            ]),
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
                    TawadType::Flat->value => 'Peso off each unit, any quantity.',
                    TawadType::MinimumQuantity->value => 'Only when they buy at least a set amount.',
                ])
                ->required()
                ->live(),
            Grid::make(2)
                ->visible(fn (Get $get): bool => $get('type') !== self::NO_TAWAD)
                ->schema([
                    TextInput::make('discount_amount')
                        ->label('Peso off per unit')
                        ->numeric()
                        ->prefix('₱')
                        ->live(debounce: 400)
                        ->helperText(fn (Get $get, Listing $record): ?string => self::ceilingHint($record, $get))
                        ->required(fn (Get $get): bool => $get('type') !== self::NO_TAWAD),
                    TextInput::make('min_quantity')
                        ->label('When they buy at least')
                        ->numeric()
                        ->live(debounce: 400)
                        ->visible(fn (Get $get): bool => $get('type') === TawadType::MinimumQuantity->value)
                        ->required(fn (Get $get): bool => $get('type') === TawadType::MinimumQuantity->value),
                ]),
            Text::make(fn (Get $get, Listing $record): string => self::buyerPays($record, $get))
                ->visible(fn (Get $get): bool => $get('type') !== self::NO_TAWAD),
        ];
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

    private static function ceilingHint(Listing $record, Get $get): ?string
    {
        try {
            $guard = $record->priceGuard();

            return $guard->hasCeiling() ? 'Biggest allowed ₱'.$guard->ceilingText() : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * What a buyer pays at a few quantities with the tawad as typed.
     */
    public static function buyerPays(Listing $record, Get $get): string
    {
        $price = is_numeric($get('price_per_unit')) ? (float) $get('price_per_unit') : (float) $record->price_per_unit;
        $off = is_numeric($get('discount_amount')) ? (float) $get('discount_amount') : 0.0;
        $minimum = $get('type') === TawadType::MinimumQuantity->value && is_numeric($get('min_quantity'))
            ? max(1.0, (float) $get('min_quantity'))
            : 1.0;
        $unit = ListingUnit::tryFrom((string) $get('unit'))?->value ?? $record->unit?->value ?? 'unit';

        $quantities = array_values(array_unique([1.0, $minimum, $minimum + 2]));

        return 'What the buyer pays: '.collect($quantities)
            ->map(function (float $quantity) use ($price, $off, $minimum, $unit): string {
                $tawad = $off > 0 && $quantity >= $minimum;
                $total = $quantity * max(0, $price - ($tawad ? $off : 0));

                return rtrim(rtrim(number_format($quantity, 2), '0'), '.')." {$unit} ₱".number_format($total, 2).($tawad ? ' with tawad' : '');
            })
            ->implode(' · ');
    }

    /**
     * The sentence above the save button: what the seller will be told.
     */
    public static function changeSummary(Listing $record, Get $get): string
    {
        $seller = $record->farmerSeller?->name ?? 'The seller';
        $changes = [];

        $price = $get('price_per_unit');
        if (is_numeric($price) && round((float) $price, 2) !== round((float) $record->price_per_unit, 2)) {
            $changes[] = 'price ₱'.number_format((float) $record->price_per_unit, 2).' → ₱'.number_format((float) $price, 2);
        }

        $stock = $get('quantity_available');
        if (is_numeric($stock) && (float) $stock !== (float) $record->quantity_available) {
            $changes[] = 'stock '.self::number($record->quantity_available).' → '.self::number($stock);
        }

        if ((bool) $get('is_active') !== (bool) $record->is_active) {
            $changes[] = $get('is_active') ? 'shown to buyers again' : 'hidden from buyers';
        }

        foreach (['title' => 'title', 'description' => 'description', 'unit' => 'unit', 'min_order_quantity' => 'minimum order', 'order_step' => 'order step', 'growing_method' => 'how it was grown', 'harvested_on' => 'harvest date', 'available_from' => 'start date', 'available_until' => 'end date'] as $field => $label) {
            $before = $record->getAttribute($field);
            $before = $before instanceof \BackedEnum ? $before->value : $before;
            $before = $before instanceof \DateTimeInterface ? $before->format($field === 'harvested_on' ? 'Y-m-d' : 'Y-m-d H:i') : $before;
            $after = $get($field);
            $after = is_string($after) && $after !== '' && $before !== null && str_contains((string) $before, '-') ? substr($after, 0, strlen((string) $before)) : $after;

            if ((string) ($before ?? '') !== (string) ($after ?? '') && ! (is_numeric($before) && is_numeric($after) && (float) $before === (float) $after)) {
                $changes[] = $label;
            }
        }

        $current = $record->activeTawadRule;
        $type = (string) $get('type');
        if ($type === self::NO_TAWAD && $current !== null) {
            $changes[] = 'tawad ended';
        } elseif ($type !== self::NO_TAWAD && ($current === null
            || $current->type?->value !== $type
            || (float) $current->discount_amount !== (float) $get('discount_amount')
            || (float) $current->min_quantity !== (float) ($type === TawadType::MinimumQuantity->value ? $get('min_quantity') : null))) {
            $changes[] = 'new tawad';
        }

        return $changes === []
            ? 'Nothing changed yet. The seller is told only what you change.'
            : "{$seller} will be told: ".implode('; ', $changes).'.';
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

    /**
     * Shows a refusal under the field it is about, inside the open panel.
     */
    private static function inModal(Action $action, Closure $run): void
    {
        try {
            $run();
        } catch (ValidationException $exception) {
            $statePath = 'mountedActions.'.($action->getNestingIndex() ?? 0).'.data.';

            throw ValidationException::withMessages(
                collect($exception->errors())
                    ->mapWithKeys(fn (array $messages, string $field): array => [$statePath.$field => $messages])
                    ->all(),
            );
        }
    }
}
