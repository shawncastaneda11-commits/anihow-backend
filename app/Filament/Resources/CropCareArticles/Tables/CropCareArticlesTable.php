<?php

namespace App\Filament\Resources\CropCareArticles\Tables;

use App\Enums\ArticleCategory;
use App\Enums\ArticleStatus;
use App\Enums\Permission;
use App\Models\CropCareArticle;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CropCareArticlesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->label('Image')
                    ->disk(config('anihow.listing_disk', 'public'))
                    ->square(),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                TextColumn::make('farm.name')
                    ->label('Farm')
                    ->sortable()
                    // Only a Super Admin sees more than one farm, so the column
                    // is noise for a Content Editor.
                    ->visible(fn (): bool => auth()->user()->can(Permission::ModerateArticles->value)),
                TextColumn::make('category')
                    ->badge()
                    ->formatStateUsing(fn (ArticleCategory $state): string => $state->label()),
                TextColumn::make('cropTypes.name')
                    ->label('Crop types')
                    ->badge()
                    ->limitList(3),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ArticleStatus $state): string => $state->label())
                    ->color(fn (ArticleStatus $state): string => match ($state) {
                        ArticleStatus::Published => 'success',
                        ArticleStatus::Draft => 'gray',
                    }),
                TextColumn::make('author.name')
                    ->label('Author')
                    ->toggleable(),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(ArticleStatus::options()),
                SelectFilter::make('category')
                    ->options(ArticleCategory::options()),
                SelectFilter::make('farm')
                    ->relationship('farm', 'name')
                    ->visible(fn (): bool => auth()->user()->can(Permission::ModerateArticles->value)),
                SelectFilter::make('crop_type')
                    ->relationship('cropTypes', 'name')
                    ->label('Crop type'),
            ])
            ->recordActions([
                /*
                 * Moderation, not editing. A Super Admin can pull a published
                 * article out of the app; they cannot rewrite farm content.
                 */
                Action::make('unpublish')
                    ->icon('heroicon-o-eye-slash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (CropCareArticle $record): bool => $record->isPublished()
                        && auth()->user()->can(Permission::ModerateArticles->value))
                    ->action(fn (CropCareArticle $record): bool => $record->update([
                        'status' => ArticleStatus::Draft,
                    ])),
                EditAction::make()
                    ->visible(fn (CropCareArticle $record): bool => auth()->user()->can('update', $record)),
                DeleteAction::make()
                    ->visible(fn (CropCareArticle $record): bool => auth()->user()->can('delete', $record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
