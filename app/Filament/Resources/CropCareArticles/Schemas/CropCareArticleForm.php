<?php

namespace App\Filament\Resources\CropCareArticles\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CropCareArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Select::make('category_id')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->helperText('Optional. Tips without a category appear under General in the app. Crop-cycle tracking is not part of this content.'),
                Textarea::make('body')
                    ->required()
                    ->rows(12)
                    ->columnSpanFull(),
                FileUpload::make('image_path')
                    ->label('Photo')
                    ->image()
                    ->disk(config('anihow.listing_disk', 'public'))
                    ->directory('crop-care')
                    ->visibility('public')
                    ->maxSize(2048)
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Published')
                    ->default(true),
            ]);
    }
}
