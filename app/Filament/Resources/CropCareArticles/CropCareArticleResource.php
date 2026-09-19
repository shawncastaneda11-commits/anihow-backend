<?php

namespace App\Filament\Resources\CropCareArticles;

use App\Enums\Permission;
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
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The Content Editor's surface. Each partner farm's editor writes that farm's
 * own crop-care and pest-management guidance, tagged to the shared taxonomy.
 * Read-only in the app.
 */
class CropCareArticleResource extends Resource
{
    protected static ?string $model = CropCareArticle::class;

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Crop care';

    protected static ?string $modelLabel = 'article';

    protected static ?string $pluralModelLabel = 'articles';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return CropCareArticleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CropCareArticlesTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can(Permission::ManageOwnFarmArticles->value) ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCropCareArticles::route('/'),
            'create' => CreateCropCareArticle::route('/create'),
            'edit' => EditCropCareArticle::route('/{record}/edit'),
        ];
    }

    /**
     * Farm scope. The policy stops a Content Editor opening another farm's
     * article; this stops the table listing one. Both are needed, and this is
     * the half that fails silently.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['farm', 'author', 'cropTypes']);
        $user = auth()->user();

        if ($user === null || $user->can(Permission::ModerateArticles->value)) {
            return $query;
        }

        return $query->where('farm_id', $user->farm_id);
    }
}
