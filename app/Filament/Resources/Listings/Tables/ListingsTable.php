<?php

namespace App\Filament\Resources\Listings\Tables;

use App\Actions\Listings\GuardListingOpenOrders;
use App\Actions\Listings\TakeDownListingAction;
use App\Actions\Reservations\GuardListingReservationCancellation;
use App\Enums\ListingStatus;
use App\Enums\TawadType;
use App\Filament\Resources\Listings\Actions\FarmListingActions;
use App\Filament\Resources\Listings\Pages\ListListings;
use App\Models\Listing;
use App\Support\InAppNotifier;
use App\Support\Pricing\UnitConverter;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListingsTable
{
    /**
     * The effective floor in SQL, for the "Priced below floor" filter.
     *
     * The main unit's floor. Extra unit rows are handled inside
     * UnitConverter::belowFloorComparison().
     *
     * Mirrors PriceGuardResolver::for(): the system floor, raised by the farm's
     * override where one exists, and never lowered by it. If that formula
     * changes, change this one too. CASE rather than GREATEST so the same SQL
     * runs on MySQL and on SQLite.
     */
    private const EFFECTIVE_FLOOR_SQL = <<<'SQL'
        CASE
            WHEN COALESCE(
                (SELECT fo.floor_price FROM farm_crop_type_overrides fo
                  WHERE fo.farm_id = listings.farm_id
                    AND fo.crop_type_id = listings.crop_type_id
                    AND fo.unit IS NULL),
                crop_types.floor_price
            ) > crop_types.floor_price
            THEN (SELECT fo.floor_price FROM farm_crop_type_overrides fo
                   WHERE fo.farm_id = listings.farm_id
                     AND fo.crop_type_id = listings.crop_type_id
                     AND fo.unit IS NULL)
            ELSE crop_types.floor_price
        END
        SQL;

    /**
     * Wrapped in brackets: the comparison joins three cases with OR, and a
     * bare OR inside whereHas() would escape the crop type's own row and
     * match every listing whenever any crop matched.
     */
    public static function belowFloorSql(): string
    {
        return '('.UnitConverter::belowFloorComparison(self::EFFECTIVE_FLOOR_SQL).')';
    }

    /** Stock at or below this shows as low. */
    public const LOW_STOCK = 5;

    /**
     * Shown, Hidden, Starts <date>, Ended, Taken down or Archived.
     */
    public static function state(Listing $record): string
    {
        return match (true) {
            $record->status === ListingStatus::TakenDown => 'Taken down',
            $record->status === ListingStatus::Archived => 'Archived',
            ! $record->is_active => 'Hidden',
            $record->isExpired() => 'Ended',
            $record->isUpcoming() => 'Starts '.$record->available_from?->format('M j'),
            default => 'Shown',
        };
    }

    public static function tawadSummary(Listing $record): string
    {
        $rule = $record->activeTawadRule;

        if ($rule === null) {
            return 'No tawad';
        }

        $off = '₱'.number_format((float) $rule->discount_amount, 2).' off';

        return $rule->type === TawadType::MinimumQuantity
            ? $off.' at '.rtrim(rtrim(number_format((float) $rule->min_quantity, 2), '0'), '.').'+'
            : $off.' every order';
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->label('')
                    ->disk(config('anihow.listing_disk', 'public'))
                    ->square()
                    ->imageSize(44)
                    ->defaultImageUrl(asset('images/anihow-mark.png')),
                // The listing, who sells it, and a red line when it is priced
                // below the lowest price.
                TextColumn::make('title')
                    ->label('Listing')
                    ->weight(FontWeight::SemiBold)
                    ->sortable()
                    ->description(fn (Listing $record): string => collect([
                        $record->farmerSeller?->name,
                        $record->cropType?->name,
                        auth()->user()?->farm_id === null ? $record->farm?->name : null,
                    ])->filter()->implode(' · '))
                    ->wrap(),
                TextColumn::make('listing_state')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn (Listing $record): string => self::state($record))
                    ->color(fn (string $state): string => match (true) {
                        $state === 'Shown' => 'success',
                        $state === 'Taken down' => 'danger',
                        str_starts_with($state, 'Starts') => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('price_per_unit')
                    ->label('Price')
                    ->sortable()
                    ->weight(FontWeight::SemiBold)
                    ->formatStateUsing(fn (Listing $record): string => '₱'.number_format((float) $record->price_per_unit, 2).' / '.($record->unit?->value ?? 'unit'))
                    ->color(fn (Listing $record): ?string => $record->isBelowFloor() ? 'danger' : null)
                    ->description(fn (Listing $record): string => $record->isBelowFloor()
                        ? 'Below the lowest price (₱'.number_format((float) $record->effectiveFloor(), 2).')'
                        : self::tawadSummary($record)),
                TextColumn::make('quantity_available')
                    ->label('Stock')
                    ->sortable()
                    ->formatStateUsing(fn (Listing $record): string => rtrim(rtrim(number_format((float) $record->quantity_available, 2), '0'), '.').' '.($record->unit?->value ?? '').' left')
                    ->color(fn (Listing $record): ?string => (float) $record->quantity_available <= self::LOW_STOCK ? 'danger' : 'gray')
                    ->description(fn (Listing $record): ?string => collect([
                        (float) $record->quantity_available <= self::LOW_STOCK ? 'Low stock' : null,
                        (float) $record->activeReservedQuantity() > 0
                            ? rtrim(rtrim(number_format((float) $record->activeReservedQuantity(), 2), '0'), '.').' reserved'
                            : null,
                    ])->filter()->implode(' · ') ?: null),
            ])
            // A Content Editor opens the panel beside the list by clicking a
            // row (ListListings::openListing). No link on the row, or the link
            // takes the click.
            ->recordUrl(null)
            ->recordAction(fn (Listing $record): ?string => FarmListingActions::canEdit($record) ? 'openListing' : null)
            ->recordClasses(fn (Listing $record, mixed $livewire): ?string => ($livewire->openId ?? null) === $record->getKey() ? 'anihow-row-on' : null)
            ->modifyQueryUsing(fn (Builder $query, mixed $livewire): Builder => $livewire instanceof ListListings
                ? $livewire->applyListFilters($query->with(['cropType', 'farm.cropTypeOverrides', 'farmerSeller', 'activeTawadRule']))
                : $query->with(['cropType', 'farm.cropTypeOverrides', 'farmerSeller', 'activeTawadRule']))
            // One "⋯" menu per row; clicking the row opens its panel.
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),

                    /*
                     * The Super Admin's only write power over a listing. The seller
                     * is notified, because a listing that vanishes with no reason
                     * is worse than one taken down with one.
                     */
                    Action::make('takedown')
                        ->icon('heroicon-o-eye-slash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (Listing $record): bool => $record->status === ListingStatus::Published
                            && (auth()->user()?->can('takedown', $record) ?? false))
                        ->modalDescription(fn (Listing $record): ?string => collect([
                            app(GuardListingReservationCancellation::class)->warning($record),
                            app(GuardListingOpenOrders::class)->warning($record),
                        ])->filter()->implode(' ') ?: null)
                        ->schema([
                            Textarea::make('takedown_reason')
                                ->label('Reason')
                                ->required()
                                ->rows(2)
                                ->helperText('Shown to the seller.'),
                            Checkbox::make('confirm_cancel_reservations')
                                ->label('I understand the reservations will be cancelled')
                                ->visible(fn (Listing $record): bool => app(GuardListingReservationCancellation::class)->describe($record) !== null)
                                ->required(fn (Listing $record): bool => app(GuardListingReservationCancellation::class)->describe($record) !== null)
                                ->accepted(fn (Listing $record): bool => app(GuardListingReservationCancellation::class)->describe($record) !== null),
                            Checkbox::make('confirm_open_orders')
                                ->label('I understand the placed orders will be cancelled')
                                ->visible(fn (Listing $record): bool => app(GuardListingOpenOrders::class)->describe($record) !== null)
                                ->required(fn (Listing $record): bool => app(GuardListingOpenOrders::class)->describe($record) !== null)
                                ->accepted(fn (Listing $record): bool => app(GuardListingOpenOrders::class)->describe($record) !== null),
                        ])
                        ->action(function (Listing $record, array $data): void {
                            app(TakeDownListingAction::class)->handle(
                                $record,
                                auth()->user(),
                                $data['takedown_reason'],
                                (bool) ($data['confirm_cancel_reservations'] ?? false),
                                (bool) ($data['confirm_open_orders'] ?? false),
                            );
                        }),

                    Action::make('restore')
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (Listing $record): bool => $record->status === ListingStatus::TakenDown
                            && (auth()->user()?->can('takedown', $record) ?? false))
                        ->action(function (Listing $record): void {
                            $record->update([
                                'status' => ListingStatus::Published,
                                'taken_down_at' => null,
                                'taken_down_by' => null,
                                'takedown_reason' => null,
                            ]);

                            app(InAppNotifier::class)->listingRestored($record->farmerSeller, $record);
                        }),
                ])->tooltip('More'),
            ])
            ->toolbarActions([]);
    }
}
