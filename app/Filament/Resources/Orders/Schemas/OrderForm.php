<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * Read-only. Used by the view action on the orders table.
 */
class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('order_number')->disabled(),
                TextInput::make('status')
                    ->formatStateUsing(fn ($state): string => $state?->label() ?? '')
                    ->disabled(),
                TextInput::make('buyer.name')->label('Buyer')->disabled(),
                TextInput::make('farmerSeller.name')->label('Seller')->disabled(),
                TextInput::make('farm.name')->label('Farm')->disabled(),
                TextInput::make('fulfillment_preference')
                    ->label('Fulfillment')
                    ->formatStateUsing(fn ($state): string => $state?->label() ?? '')
                    ->disabled(),
                Textarea::make('fulfillment_note')
                    ->label('Arrangement note')
                    ->rows(2)
                    ->disabled()
                    ->columnSpanFull(),
                TextInput::make('subtotal')->prefix('PHP')->disabled(),
                TextInput::make('tawad_total')->label('Tawad')->prefix('PHP')->disabled(),
                TextInput::make('total')->prefix('PHP')->disabled(),
                TextInput::make('amount_received')
                    ->label('Cash received at handover')
                    ->prefix('PHP')
                    ->disabled(),
                Textarea::make('cancellation_note')
                    ->rows(2)
                    ->disabled()
                    ->visible(fn (?Order $record): bool => filled($record?->cancellation_note))
                    ->columnSpanFull(),
            ]);
    }
}
