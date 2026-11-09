<?php

namespace App\Filament\Resources\SellerRequests;

use App\Enums\Permission;
use App\Enums\SellerRequestStatus;
use App\Filament\Resources\SellerRequests\Pages\ListSellerRequests;
use App\Models\SellerRequest;
use App\Models\User;
use App\Support\Accounts\AccountAuthority;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Content Editors ask for new farmer-sellers on their own farm; COSEL adds
 * them or says why not. COSEL sees every farm's requests, a Content Editor
 * their own farm's.
 */
class SellerRequestResource extends Resource
{
    protected static ?string $model = SellerRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static string|UnitEnum|null $navigationGroup = 'People';

    protected static ?string $navigationLabel = 'Seller requests';

    protected static ?string $modelLabel = 'seller request';

    protected static ?string $pluralModelLabel = 'seller requests';

    protected static ?int $navigationSort = 2;

    public static function getPages(): array
    {
        return [
            'index' => ListSellerRequests::route('/'),
        ];
    }

    public static function decides(?User $user): bool
    {
        return AccountAuthority::managesPeople($user);
    }

    public static function asks(?User $user): bool
    {
        return ($user?->can(Permission::RequestFarmerSellers->value) ?? false) && $user->farm_id !== null;
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return self::decides($user) || self::asks($user);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['farm', 'requester', 'decider']);
        $user = auth()->user();

        if (self::decides($user)) {
            return $query;
        }

        return $user?->farm_id !== null ? $query->where('farm_id', $user->farm_id) : $query->whereRaw('1 = 0');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /** COSEL sees how many requests are waiting. */
    public static function getNavigationBadge(): ?string
    {
        if (! self::decides(auth()->user())) {
            return null;
        }

        $waiting = SellerRequest::query()->where('status', SellerRequestStatus::Waiting)->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
