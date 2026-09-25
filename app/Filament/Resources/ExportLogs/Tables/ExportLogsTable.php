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
                TextColumn::make('filters')
                    ->label('Filters')
                    ->formatStateUsing(function (ExportLog $record): string {
                        $filters = $record->filters ?? [];

                        return $filters === [] ? '—' : json_encode($filters, JSON_UNESCAPED_SLASHES);
                    })
                    ->wrap(),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
