<?php

namespace App\Filament\Resources\FaqEntries\Schemas;

use App\Enums\Role;
use App\Models\FaqEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class FaqEntryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('intent_key')
                    ->label('Intent key'),
                TextEntry::make('farm.name')
                    ->label('Scope')
                    ->placeholder('System-wide')
                    ->formatStateUsing(function (?string $state, FaqEntry $record): string {
                        if ($record->isSystemWide()) {
                            return 'System-wide';
                        }

                        return $state ?? 'Farm';
                    }),
                TextEntry::make('roles')
                    ->label('Visible to')
                    ->formatStateUsing(function (mixed $state): string {
                        $roles = is_array($state) ? $state : [];

                        return collect($roles)
                            ->map(fn (string $role): string => Role::tryFrom($role)?->label() ?? $role)
                            ->implode(', ');
                    }),
                TextEntry::make('is_active')
                    ->label('Active')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No'),
                Grid::make(2)
                    ->schema([
                        TextEntry::make('label')
                            ->label('Label (English)'),
                        TextEntry::make('label_fil')
                            ->label('Label (Filipino)'),
                    ]),
                Grid::make(2)
                    ->schema([
                        TextEntry::make('answer')
                            ->label('Answer (English)')
                            ->markdown(),
                        TextEntry::make('answer_fil')
                            ->label('Answer (Filipino)')
                            ->markdown(),
                    ]),
                TextEntry::make('keywords')
                    ->label('Keywords')
                    ->formatStateUsing(function (mixed $state): string {
                        $keywords = is_array($state) ? $state : [];

                        return implode(', ', $keywords);
                    }),
            ]);
    }
}
