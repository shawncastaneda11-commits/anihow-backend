<?php

namespace App\Filament\Resources\Farms\Pages;

use App\Filament\Resources\Farms\FarmResource;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CreateFarm extends CreateRecord
{
    protected static string $resource = FarmResource::class;

    protected static bool $canCreateAnother = false;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(150)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (?string $state, callable $set): mixed => $set('slug', Str::slug((string) $state))),
            TextInput::make('slug')
                ->required()
                ->unique()
                ->maxLength(160)
                ->helperText('Used in article URLs, so two farms can publish a guide with the same title.'),
            TextInput::make('municipality')
                ->maxLength(100)
                ->default('General Trias'),
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResourceUrl('edit');
    }
}
