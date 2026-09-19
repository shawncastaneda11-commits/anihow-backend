<?php

namespace App\Filament\Resources\Listings\Schemas;

use App\Models\Listing;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * Read-only. Used by the view action on the listings table. Every field is
 * disabled because moderation is takedown, not editing.
 */
class ListingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')->disabled(),
                TextInput::make('cropType.name')
                    ->label('Crop type')
                    ->disabled(),
                TextInput::make('farmerSeller.name')
                    ->label('Seller')
                    ->disabled(),
                TextInput::make('farm.name')
                    ->label('Farm')
                    ->disabled(),
                TextInput::make('price_per_unit')
                    ->label('Price per unit')
                    ->prefix('PHP')
                    ->disabled(),
                TextInput::make('quantity_available')
                    ->label('Quantity available')
                    ->disabled(),
                TextInput::make('quantity_held')
                    ->label('Held by placed orders')
                    ->disabled(),
                Textarea::make('description')
                    ->rows(4)
                    ->disabled()
                    ->columnSpanFull(),
                Textarea::make('takedown_reason')
                    ->rows(2)
                    ->disabled()
                    ->visible(fn (?Listing $record): bool => filled($record?->takedown_reason))
                    ->columnSpanFull(),
            ]);
    }
}
