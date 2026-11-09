<?php

namespace App\Filament\Resources\Payments;

use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Tables\PaymentsTable;
use App\Models\PaymentProof;
use App\Support\Sales\SalesAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Read-only payment ledger. Super Admin only. Nothing here moves money.
 */
class PaymentResource extends Resource
{
    protected static ?string $model = PaymentProof::class;

    protected static ?string $slug = 'payments';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Marketplace';

    protected static ?string $navigationLabel = 'Payments';

    protected static ?string $modelLabel = 'payment';

    protected static ?string $pluralModelLabel = 'payments';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return PaymentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with([
            'order.farm',
            'order.buyer',
            'order.farmerSeller',
            'reservation.listing',
            'reservation.farm',
            'buyer',
            'farmerSeller',
            'paymentQr',
        ]);
        $user = auth()->user();

        if (SalesAccess::seesAll($user)) {
            return $query;
        }

        $farmId = SalesAccess::ownFarmId($user);

        if ($farmId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(fn (Builder $proofs): Builder => $proofs
            ->whereHas('order', fn (Builder $order): Builder => $order->where('farm_id', $farmId))
            ->orWhereHas('reservation', fn (Builder $reservation): Builder => $reservation->where('farm_id', $farmId)));
    }

    /**
     * COSEL sees every payment in full. A Content Editor sees their farm's
     * payment status only: no screenshots or reference numbers, which can
     * carry the buyer's wallet name and number.
     */
    public static function canAccess(): bool
    {
        return SalesAccess::canOpen(auth()->user());
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
