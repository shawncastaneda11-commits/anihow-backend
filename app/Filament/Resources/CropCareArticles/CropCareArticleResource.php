<?php

namespace App\Filament\Resources\CropCareArticles;

use App\Filament\Resources\CropCareArticles\Pages\CreateCropCareArticle;
use App\Filament\Resources\CropCareArticles\Pages\EditCropCareArticle;
use App\Filament\Resources\CropCareArticles\Pages\ListCropCareArticles;
use App\Filament\Resources\CropCareArticles\Schemas\CropCareArticleForm;
use App\Filament\Resources\CropCareArticles\Tables\CropCareArticlesTable;
use App\Models\CropCareArticle;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CropCareArticleResource extends Resource
{
    protected static ?string $model = CropCareArticle::class;

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Crop care';

    protected static ?string $modelLabel = 'crop-care article';

    protected static ?string $pluralModelLabel = 'crop-care articles';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return CropCareArticleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CropCareArticlesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCropCareArticles::route('/'),
            'create' => CreateCropCareArticle::route('/create'),
            'edit' => EditCropCareArticle::route('/{record}/edit'),
        ];
    }
}
