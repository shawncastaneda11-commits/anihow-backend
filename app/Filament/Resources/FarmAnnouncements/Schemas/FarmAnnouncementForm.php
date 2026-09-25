<?php

namespace App\Filament\Resources\FarmAnnouncements\Schemas;

use App\Enums\AnnouncementAudience;
use App\Enums\Permission;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class FarmAnnouncementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('farm_id')
                    ->label('Farm')
                    ->relationship('farm', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->visible(fn (): bool => auth()->user()?->can(Permission::ManageFarms->value) ?? false)
                    ->helperText('A Content Editor never picks this. Their farm is set on save.'),

                TextInput::make('title')
                    ->required()
                    ->maxLength(120),

                Textarea::make('body')
                    ->required()
                    ->rows(6)
                    ->maxLength(1000)
                    ->columnSpanFull(),

                Select::make('audience')
                    ->options(AnnouncementAudience::options())
                    ->required()
                    ->native(false)
                    ->helperText('Members: farmer-sellers of this farm. Public: also shown on the farm page.'),

                DateTimePicker::make('starts_at')
                    ->label('Starts at')
                    ->helperText('Leave blank to start immediately.'),

                DateTimePicker::make('ends_at')
                    ->label('Ends at')
                    ->helperText('Leave blank to stay up until you take it down.'),

                Toggle::make('is_pinned')
                    ->label('Pinned')
                    ->default(false)
                    ->helperText('Pinned announcements appear first for farmer-sellers.'),
            ]);
    }
}
