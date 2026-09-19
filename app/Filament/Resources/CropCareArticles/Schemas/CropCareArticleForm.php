<?php

namespace App\Filament\Resources\CropCareArticles\Schemas;

use App\Enums\ArticleCategory;
use App\Enums\ArticleStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CropCareArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(200)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (?string $state, callable $set) => $set('slug', Str::slug((string) $state))),

                TextInput::make('slug')
                    ->required()
                    ->maxLength(220)
                    ->helperText('Unique within this farm. Two farms may both publish a guide with the same title.'),

                Select::make('category')
                    ->options(ArticleCategory::options())
                    ->required()
                    ->native(false),

                /*
                 * Articles belong to one farm but tag to the shared taxonomy.
                 * One article may cover several crops.
                 */
                Select::make('cropTypes')
                    ->label('Crop types')
                    ->relationship('cropTypes', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->required()
                    ->helperText('Readers reach this article from the crop types it is tagged to.'),

                TextInput::make('excerpt')
                    ->maxLength(300)
                    ->columnSpanFull()
                    ->helperText('Optional. Leave blank and the list view derives one from the body.'),

                Textarea::make('body')
                    ->required()
                    ->rows(14)
                    ->columnSpanFull(),

                FileUpload::make('image_path')
                    ->label('Image')
                    ->image()
                    ->disk(config('anihow.listing_disk', 'public'))
                    ->directory('crop-care')
                    ->visibility('public')
                    ->maxSize(2048)
                    ->columnSpanFull(),

                Select::make('status')
                    ->options(ArticleStatus::options())
                    ->default(ArticleStatus::Draft->value)
                    ->required()
                    ->native(false)
                    ->live()
                    ->helperText('Only published articles appear in the app.'),

                DateTimePicker::make('published_at')
                    ->label('Published at')
                    ->visible(fn (callable $get): bool => $get('status') === ArticleStatus::Published->value)
                    ->helperText('Leave blank to stamp the current time on save.'),
            ]);
    }
}
