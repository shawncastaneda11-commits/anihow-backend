<?php

namespace App\Filament\Resources\Reviews\Tables;

use App\Enums\Permission;
use App\Models\Review;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ReviewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('order.order_number')
                    ->label('Order')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('rating')
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => str_repeat('*', $state).' '.$state)
                    ->color(fn (int $state): string => match (true) {
                        $state >= 4 => 'success',
                        $state === 3 => 'warning',
                        default => 'danger',
                    })
                    ->sortable(),
                TextColumn::make('comment')
                    ->limit(60)
                    ->searchable()
                    ->wrap(),
                TextColumn::make('buyer.name')
                    ->label('Buyer')
                    ->searchable(),
                TextColumn::make('farmerSeller.name')
                    ->label('Seller')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('is_removed')
                    ->label('State')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Removed' : 'Visible')
                    ->color(fn (bool $state): string => $state ? 'danger' : 'success'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_removed')
                    ->label('Removed'),
                SelectFilter::make('rating')
                    ->options([1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5']),
            ])
            ->recordActions([
                Action::make('remove')
                    ->icon('heroicon-o-eye-slash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Review $record): bool => ! $record->is_removed
                        && auth()->user()->can(Permission::ModerateReviews->value))
                    ->schema([
                        Textarea::make('removal_reason')
                            ->label('Reason')
                            ->required()
                            ->rows(2),
                    ])
                    ->action(fn (Review $record, array $data): bool => $record->update([
                        'is_removed' => true,
                        'removed_by' => auth()->id(),
                        'removed_at' => now(),
                        'removal_reason' => $data['removal_reason'],
                    ])),
                Action::make('restore')
                    ->icon('heroicon-o-eye')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Review $record): bool => $record->is_removed)
                    ->action(fn (Review $record): bool => $record->update([
                        'is_removed' => false,
                        'removed_by' => null,
                        'removed_at' => null,
                        'removal_reason' => null,
                    ])),
            ])
            ->toolbarActions([]);
    }
}
