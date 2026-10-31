<?php

namespace App\Filament\Resources\ExportLogs\Tables;

use App\Enums\ExportType;
use App\Models\ExportLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExportLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Exported')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('By')
                    ->searchable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (ExportType $state): string => $state->label()),
                TextColumn::make('row_count')
                    ->label('Rows')
                    ->sortable(),
                // The saved filters are an array, which a plain column would
                // render once per key. Show one readable line instead.
                TextColumn::make('filters')
                    ->label('Filters')
                    ->state(fn (ExportLog $record): string => $record->filterSummary())
                    ->wrap(),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
