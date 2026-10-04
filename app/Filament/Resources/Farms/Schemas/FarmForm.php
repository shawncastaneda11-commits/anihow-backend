<?php

namespace App\Filament\Resources\Farms\Schemas;

use App\Models\Farm;
use App\Policies\FarmPolicy;
use App\Support\ImageVariants;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class FarmForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(150)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (?string $state, callable $set) => $set('slug', Str::slug((string) $state))),
                TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(160)
                    ->helperText('Used in article URLs, so two farms can publish a guide with the same title.'),
                Textarea::make('description')
                    ->rows(4)
                    ->columnSpanFull()
                    ->helperText('Shown publicly in the app.'),
                TextInput::make('contact_person')
                    ->maxLength(150)
                    ->helperText('Shown publicly in the app.'),
                TextInput::make('contact_number')
                    ->tel()
                    ->maxLength(30)
                    ->helperText("Visible only to this farm's farmer-sellers and the Super Admin."),
                TextInput::make('address')
                    ->maxLength(255),
                TextInput::make('barangay')
                    ->maxLength(100),
                TextInput::make('municipality')
                    ->maxLength(100)
                    ->default('General Trias'),
                TextInput::make('pickup_point')
                    ->maxLength(255)
                    ->columnSpanFull()
                    ->helperText('Shown publicly in the app. Where buyers meet sellers. Free text: no map, no route, no coordinates.'),
                ImageVariants::bindUpload(
                    FileUpload::make('cover_photo_path')
                        ->label('Cover photo')
                        ->image()
                        ->disk(config('anihow.listing_disk', 'public'))
                        ->directory('farms')
                        ->visibility('public')
                        ->maxSize(2048)
                        ->columnSpanFull()
                        ->helperText('Shown publicly in the app.'),
                    'farms',
                ),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),

                TextInput::make('organic_certifier')
                    ->label('Organic certifier')
                    ->maxLength(150)
                    ->visible(fn (?Farm $record): bool => self::canManageOrganicCertification($record))
                    ->dehydrated(fn (?Farm $record): bool => self::canManageOrganicCertification($record))
                    ->helperText('The body that certified this farm. Leave blank when the farm is not certified.'),
                TextInput::make('organic_certificate_no')
                    ->label('Organic certificate number')
                    ->maxLength(100)
                    ->visible(fn (?Farm $record): bool => self::canManageOrganicCertification($record))
                    ->dehydrated(fn (?Farm $record): bool => self::canManageOrganicCertification($record)),
                DatePicker::make('organic_certified_until')
                    ->label('Organic certificate valid until')
                    ->native(false)
                    ->visible(fn (?Farm $record): bool => self::canManageOrganicCertification($record))
                    ->dehydrated(fn (?Farm $record): bool => self::canManageOrganicCertification($record))
                    ->helperText('A listing may say certified organic only while this date is today or later, and the certifier and number are both filled in.'),
            ]);
    }

    private static function canManageOrganicCertification(?Farm $record): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return app(FarmPolicy::class)->manageOrganicCertification($user, $record);
    }
}
