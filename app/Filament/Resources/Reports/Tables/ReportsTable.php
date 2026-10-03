<?php

namespace App\Filament\Resources\Reports\Tables;

use App\Actions\Reports\DismissReportAction;
use App\Actions\Reports\ResolveReportAction;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Filament\Resources\Listings\ListingResource;
use App\Filament\Resources\Reviews\ReviewResource;
use App\Models\Listing;
use App\Models\Report;
use App\Models\Review;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ReportStatus $state): string => $state->label())
                    ->color(fn (ReportStatus $state): string => match ($state) {
                        ReportStatus::Open => 'danger',
                        ReportStatus::Resolved => 'success',
                        ReportStatus::Dismissed => 'gray',
                    }),
                TextColumn::make('target')
                    ->label('Target')
                    ->state(fn (Report $record): string => self::targetLabel($record))
                    ->url(fn (Report $record): ?string => self::targetUrl($record)),
                TextColumn::make('reason')
                    ->formatStateUsing(fn (ReportReason|string $state): string => $state instanceof ReportReason
                        ? $state->label()
                        : $state)
                    ->searchable()
                    ->wrap(),
                TextColumn::make('details')
                    ->limit(80)
                    ->wrap()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('reporter.name')
                    ->label('Reported by')
                    ->searchable(),
                TextColumn::make('resolvedBy.name')
                    ->label('Resolved by')
                    ->placeholder('Open')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(ReportStatus::options()),
            ])
            ->recordActions([
                Action::make('resolve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Report $record): bool => $record->status === ReportStatus::Open)
                    ->schema([
                        Textarea::make('resolution_note')
                            ->label('What was done')
                            ->required()
                            ->rows(2),
                        Toggle::make('apply_moderation')
                            ->label(fn (Report $record): string => $record->reportable instanceof Review
                                ? 'Remove the review'
                                : 'Take down the listing')
                            ->visible(fn (Report $record): bool => $record->reportable instanceof Listing
                                || $record->reportable instanceof Review)
                            ->live(),
                        Textarea::make('moderation_reason')
                            ->label(fn (Report $record): string => $record->reportable instanceof Review
                                ? 'Removal reason'
                                : 'Takedown reason')
                            ->helperText(fn (Report $record): ?string => $record->reportable instanceof Listing
                                ? 'Shown to the seller.'
                                : null)
                            ->required(fn (Get $get): bool => (bool) $get('apply_moderation'))
                            ->visible(fn (Get $get): bool => (bool) $get('apply_moderation'))
                            ->rows(2),
                    ])
                    ->action(function (Report $record, array $data): void {
                        app(ResolveReportAction::class)->handle(
                            $record,
                            auth()->user(),
                            $data['resolution_note'],
                            (bool) ($data['apply_moderation'] ?? false),
                            $data['moderation_reason'] ?? null,
                        );
                    }),
                Action::make('dismiss')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (Report $record): bool => $record->status === ReportStatus::Open)
                    ->action(function (Report $record): void {
                        app(DismissReportAction::class)->handle($record, auth()->user());
                    }),
            ])
            ->toolbarActions([]);
    }

    private static function targetLabel(Report $record): string
    {
        $target = $record->reportable;

        if ($target instanceof Listing) {
            return 'Listing: '.$target->title;
        }

        if ($target instanceof Review) {
            return 'Review #'.$target->id;
        }

        return class_basename((string) $record->reportable_type);
    }

    private static function targetUrl(Report $record): ?string
    {
        $target = $record->reportable;

        if ($target instanceof Listing) {
            return ListingResource::getUrl('index');
        }

        if ($target instanceof Review) {
            return ReviewResource::getUrl('index');
        }

        return null;
    }
}
