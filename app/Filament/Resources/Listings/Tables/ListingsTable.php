<?php

namespace App\Filament\Resources\Listings\Tables;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Support\InAppNotifier;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->label('Image')
                    ->disk(config('anihow.listing_disk', 'public'))
                    ->square(),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('cropType.name')
                    ->label('Crop type')
                    ->sortable(),
                TextColumn::make('farmerSeller.name')
                    ->label('Seller')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('farm.name')
                    ->label('Farm')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('price_per_unit')
                    ->label('Price')
                    ->money('PHP')
                    ->sortable()
                    // A listing priced below its crop type's floor is stranded,
                    // usually because the Super Admin raised the floor.
                    ->color(fn (Listing $record): ?string => $record->isBelowFloor() ? 'danger' : null)
                    ->description(fn (Listing $record): ?string => $record->isBelowFloor()
                        ? 'Below floor of PHP '.number_format((float) $record->cropType->floor_price, 2)
                        : null),
                TextColumn::make('quantity_available')
                    ->label('Qty')
                    ->sortable(),
                TextColumn::make('quantity_held')
                    ->label('Held')
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ListingStatus $state): string => $state->label())
                    ->color(fn (ListingStatus $state): string => match ($state) {
                        ListingStatus::Published => 'success',
                        ListingStatus::TakenDown => 'danger',
                        ListingStatus::Archived => 'gray',
                    }),
                IconColumn::make('is_active')
                    ->label('Seller active')
                    ->boolean()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(ListingStatus::options()),
                SelectFilter::make('crop_type')
                    ->relationship('cropType', 'name')
                    ->label('Crop type'),
                SelectFilter::make('farm')
                    ->relationship('farm', 'name'),
                TernaryFilter::make('is_active')
                    ->label('Seller active'),
                Filter::make('below_floor')
                    ->label('Priced below floor')
                    ->query(fn (Builder $query): Builder => $query->whereHas(
                        'cropType',
                        fn (Builder $cropType): Builder => $cropType->whereColumn(
                            'crop_types.floor_price',
                            '>',
                            'listings.price_per_unit',
                        ),
                    )),
            ])
            ->recordActions([
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
                    ->visible(fn (Listing $record): bool => $record->status === ListingStatus::Published)
                    ->schema([
                        Textarea::make('takedown_reason')
                            ->label('Reason')
                            ->required()
                            ->rows(2)
                            ->helperText('Shown to the seller.'),
                    ])
                    ->action(function (Listing $record, array $data): void {
                        $record->update([
                            'status' => ListingStatus::TakenDown,
                            'taken_down_at' => now(),
                            'taken_down_by' => auth()->id(),
                            'takedown_reason' => $data['takedown_reason'],
                        ]);

                        app(InAppNotifier::class)->listingTakenDown($record->farmerSeller, $record);
                    }),

                Action::make('restore')
                    ->icon('heroicon-o-eye')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Listing $record): bool => $record->status === ListingStatus::TakenDown)
                    ->action(fn (Listing $record): bool => $record->update([
                        'status' => ListingStatus::Published,
                        'taken_down_at' => null,
                        'taken_down_by' => null,
                        'takedown_reason' => null,
                    ])),
            ])
            ->toolbarActions([]);
    }
}
