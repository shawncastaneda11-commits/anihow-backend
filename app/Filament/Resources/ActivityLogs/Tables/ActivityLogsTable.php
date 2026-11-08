<?php

namespace App\Filament\Resources\ActivityLogs\Tables;

use App\Models\ActivityLog;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActivityLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('By')
                    ->placeholder('Deleted account')
                    ->searchable(),
                TextColumn::make('action')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'deleted' => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (string $state): string => ActivityLog::ACTIONS[$state] ?? ucfirst(str_replace('_', ' ', $state))),
                TextColumn::make('subject_type')
                    ->label('Record')
                    ->state(fn (ActivityLog $record): string => $record->subjectName()
                        .match (true) {
                            filled($record->subject_label) => ': '.$record->subject_label,
                            $record->subject_id !== null => ' #'.$record->subject_id,
                            default => '',
                        }),
                TextColumn::make('changes')
                    ->label('Changes')
                    ->state(fn (ActivityLog $record): string => $record->changeSummary())
                    ->wrap()
                    ->limit(240),
            ])
            ->filters([
                SelectFilter::make('user')
                    ->label('By')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('action')
                    ->options(ActivityLog::ACTIONS),
                SelectFilter::make('subject_type')
                    ->label('Record type')
                    ->options(fn (): array => ActivityLog::query()
                        ->when(auth()->user() instanceof User, fn ($query) => $query->visibleTo(auth()->user()))
                        ->distinct()
                        ->pluck('subject_type')
                        ->mapWithKeys(fn (string $type): array => [$type => (new ActivityLog(['subject_type' => $type]))->subjectName()])
                        ->all()),
                Filter::make('when')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('created_at', '<=', $date))),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
