<?php

namespace App\Filament\Resources\Listings\Schemas;

use App\Enums\ListingUnit;
use App\Enums\Role;
use App\Models\User;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ListingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('farmer_seller_id')
                    ->label('Farmer-seller')
                    ->relationship(
                        name: 'farmerSeller',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn ($query) => $query->whereHas(
                            'roles',
                            fn ($roles) => $roles->where('name', Role::FarmerSeller->value),
                        ),
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn (User $record): string => $record->name.($record->location ? ' — '.$record->location : ''),
                    )
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('category_id')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Select::make('unit')
                    ->options(ListingUnit::options())
                    ->required()
                    ->native(false),
                TextInput::make('price_per_unit')
                    ->label('Price per unit (PHP)')
                    ->numeric()
                    ->prefix('₱')
                    ->required()
                    ->minValue(0.01),
                TextInput::make('quantity_available')
                    ->numeric()
                    ->required()
                    ->minValue(0),
                Textarea::make('description')
                    ->rows(4)
                    ->columnSpanFull(),
                FileUpload::make('image_path')
                    ->label('Image')
                    ->image()
                    ->disk(config('anihow.listing_disk', 'public'))
                    ->directory('listings')
                    ->visibility('public')
                    ->maxSize(2048)
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Active on marketplace')
                    ->default(true),
            ]);
    }
}
