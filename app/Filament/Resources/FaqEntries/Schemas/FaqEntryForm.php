<?php

namespace App\Filament\Resources\FaqEntries\Schemas;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\FaqEntry;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class FaqEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        $managesSystem = auth()->user()?->can(Permission::ManageSystemFaq->value) ?? false;

        return $schema
            ->components([
                Placeholder::make('moderation_notice')
                    ->label('')
                    ->content(function (?FaqEntry $record): string {
                        $date = $record?->moderated_at?->toFormattedDateString() ?? '';

                        return "Hidden by the Super Admin on {$date}. Edit the answer, then ask the Super Admin to reactivate it.";
                    })
                    ->visible(fn (?FaqEntry $record): bool => $record?->isModerated() ?? false)
                    ->columnSpanFull(),

                TextInput::make('intent_key')
                    ->label('Intent key')
                    ->required()
                    ->maxLength(80)
                    ->regex('/^[a-z0-9_]+$/')
                    ->helperText('Use the same key as a system row to override it for this farm. Letters, numbers, and underscores only.')
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: function (Unique $rule): Unique {
                            $user = auth()->user();
                            $farmId = ($user?->can(Permission::ManageSystemFaq->value) ?? false)
                                ? null
                                : $user?->farm_id;

                            if ($farmId === null) {
                                return $rule->whereNull('farm_id');
                            }

                            return $rule->where('farm_id', $farmId);
                        },
                    ),

                CheckboxList::make('roles')
                    ->label('Visible to')
                    ->options([
                        Role::Buyer->value => Role::Buyer->label(),
                        Role::FarmerSeller->value => Role::FarmerSeller->label(),
                    ])
                    ->required()
                    ->visible($managesSystem)
                    ->helperText('Buyer answers stay system-wide. Farm rows are farmer-seller only.'),

                Grid::make(2)
                    ->schema([
                        TextInput::make('label')
                            ->label('Label (English)')
                            ->required()
                            ->maxLength(120),
                        TextInput::make('label_fil')
                            ->label('Label (Filipino)')
                            ->required()
                            ->maxLength(120),
                    ]),

                Grid::make(2)
                    ->schema([
                        Textarea::make('answer')
                            ->label('Answer (English)')
                            ->required()
                            ->rows(8),
                        Textarea::make('answer_fil')
                            ->label('Answer (Filipino)')
                            ->required()
                            ->rows(8),
                    ]),

                TagsInput::make('keywords')
                    ->label('Keywords')
                    ->required()
                    ->splitKeys(['Tab', 'Enter', ','])
                    ->helperText('Phrases that match this intent. Press Enter after each one.'),

                TextInput::make('sort_order')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->required(),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->helperText(fn (?FaqEntry $record): string => $record?->isModerated()
                        ? 'Your Active toggle does not override Super Admin hide.'
                        : 'Inactive rows are hidden from the FAQ bot.'),
            ]);
    }
}
