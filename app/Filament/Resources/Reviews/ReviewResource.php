<?php

namespace App\Filament\Resources\Reviews;

use App\Enums\Permission;
use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Filament\Resources\Reviews\Tables\ReviewsTable;
use App\Models\Review;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Moderation only. Reviews are tied to completed orders and are not editable
 * by anyone, including their author: rewriting a review after the transaction
 * would make the rating meaningless. Removal is soft, so the order keeps its
 * one-review link and the buyer cannot post a replacement.
 */
class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static string|UnitEnum|null $navigationGroup = 'Marketplace';

    protected static ?string $navigationLabel = 'Reviews';

    protected static ?string $modelLabel = 'review';

    protected static ?string $pluralModelLabel = 'reviews';

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return ReviewsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReviews::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['buyer', 'farmerSeller', 'order']);
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::ModerateReviews->value) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }
}
