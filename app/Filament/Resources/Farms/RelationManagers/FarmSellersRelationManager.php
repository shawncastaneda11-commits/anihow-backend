<?php

namespace App\Filament\Resources\Farms\RelationManagers;

use App\Filament\Resources\SellerRequests\SellerRequestResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Support\Accounts\AccountAuthority;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The farm's farmer-sellers, read here; their accounts change on the
 * Accounts screen (COSEL) or by a seller request (the Content Editor).
 */
class FarmSellersRelationManager extends RelationManager
{
    protected static string $relationship = 'farmerSellers';

    protected static ?string $title = 'Sellers';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view', $ownerRecord) ?? false;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(fn (): string => $this->getOwnerRecord()->farmerSellers()->count().' farmer-sellers on this farm')
            ->defaultSort('name')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->withCount(['listings' => fn (Builder $listings): Builder => $listings->where('is_active', true)]))
            ->columns([
                TextColumn::make('name')
                    ->weight(FontWeight::SemiBold)
                    ->description(fn (User $record): string => (string) $record->email),
                TextColumn::make('status')
                    ->badge()
                    ->state(fn (User $record): string => match (true) {
                        ! $record->isActive() => 'Suspended',
                        (bool) $record->must_change_password => 'Hasn\'t signed in yet',
                        default => 'Active',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Suspended' => 'danger',
                        'Active' => 'success',
                        default => 'warning',
                    }),
                TextColumn::make('listings_count')
                    ->label('Listings shown')
                    ->color('gray'),
            ])
            ->headerActions([
                // COSEL adds accounts; a Content Editor asks COSEL to.
                Action::make('addSellers')
                    ->label(fn (): string => AccountAuthority::managesPeople(auth()->user()) ? 'Add many farmer-sellers' : 'Ask COSEL to add sellers')
                    ->color('gray')
                    ->url(fn (): ?string => AccountAuthority::managesPeople(auth()->user())
                        ? UserResource::getUrl()
                        : (SellerRequestResource::asks(auth()->user()) ? SellerRequestResource::getUrl() : null))
                    ->visible(fn (): bool => AccountAuthority::managesPeople(auth()->user()) || SellerRequestResource::asks(auth()->user())),
            ]);
    }
}
