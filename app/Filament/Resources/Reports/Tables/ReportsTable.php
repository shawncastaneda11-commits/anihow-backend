<?php

namespace App\Filament\Resources\Reports\Tables;

use App\Enums\ReportStatus;
use App\Models\Report;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
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
                TextColumn::make('reportable_type')
                    ->label('Target')
                    ->formatStateUsing(fn (string $state): string => class_basename($state)),
                TextColumn::make('reason')
                    ->searchable()
                    ->limit(40)
                    ->wrap(),
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
                            ->rows(2)
                            ->helperText('Take down the listing or remove the review separately. This records the outcome.'),
                    ])
                    ->action(fn (Report $record, array $data): bool => $record->update([
                        'status' => ReportStatus::Resolved,
                        'resolved_by' => auth()->id(),
                        'resolution_note' => $data['resolution_note'],
                        'resolved_at' => now(),
                    ])),
                Action::make('dismiss')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (Report $record): bool => $record->status === ReportStatus::Open)
                    ->action(fn (Report $record): bool => $record->update([
                        'status' => ReportStatus::Dismissed,
                        'resolved_by' => auth()->id(),
                        'resolved_at' => now(),
                    ])),
            ])
            ->toolbarActions([]);
    }
}
