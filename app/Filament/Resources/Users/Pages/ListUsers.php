<?php

namespace App\Filament\Resources\Users\Pages;

use App\Actions\Accounts\BulkCreateFarmerSellers;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Filament\Resources\Users\UserResource;
use App\Models\Farm;
use App\Support\Accounts\AccountAuthority;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // COSEL only: many farmer-sellers for one farm at once.
            Action::make('bulkCreateFarmerSellers')
                ->label('Add many farmer-sellers')
                ->icon('heroicon-o-user-group')
                ->color('gray')
                ->visible(fn (): bool => AccountAuthority::managesPeople(auth()->user()))
                ->modalDescription('Paste one person per line: name, email, and mobile (optional), separated by commas or copied straight from a spreadsheet. '
                    .'No password is created you can see: hand each person a temporary password with "Reset temporary password" when you meet them.')
                ->schema([
                    Select::make('farm_id')
                        ->label('Farm')
                        ->options(fn (): array => Farm::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->required()
                        ->searchable()
                        ->native(false),
                    Textarea::make('rows')
                        ->label('People')
                        ->required()
                        ->rows(10)
                        ->placeholder("Juan Dela Cruz, juan@example.com, 09171234567\nMaria Santos, maria@example.com"),
                ])
                ->action(function (array $data): void {
                    abort_unless(AccountAuthority::managesPeople(auth()->user()), 403);

                    $result = app(BulkCreateFarmerSellers::class)
                        ->handle(Farm::query()->findOrFail($data['farm_id']), $data['rows']);

                    $created = count($result['created']);
                    $notification = Notification::make()
                        ->title("{$created} farmer-seller accounts created.")
                        ->body($result['skipped'] === [] ? null : 'Not created: '.implode(' ', $result['skipped']))
                        ->persistent();

                    ($result['skipped'] === [] ? $notification->success() : $notification->warning())->send();
                }),
            CreateAction::make()
                ->label('Create account'),
        ];
    }

    /**
     * COSEL's role chips. ICTD's list is administrators only, so it has none.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        if (! AccountAuthority::managesPeople(auth()->user())) {
            return [];
        }

        $role = fn (Role $role): Closure => fn (Builder $query): Builder => $query->whereHas(
            'roles',
            fn (Builder $roles): Builder => $roles->where('name', $role->value),
        );

        $tabs = [
            'all' => ['All', fn (Builder $query): Builder => $query],
            'sellers' => ['Farmer-sellers', $role(Role::FarmerSeller)],
            'buyers' => ['Buyers', $role(Role::Buyer)],
            'editors' => ['Content Editors', $role(Role::ContentEditor)],
            'suspended' => ['Suspended', fn (Builder $query): Builder => $query->where('status', UserStatus::Suspended)],
        ];

        return collect($tabs)
            ->map(fn (array $tab): Tab => Tab::make($tab[0])
                ->modifyQueryUsing($tab[1])
                ->badge(fn (): int => $tab[1](UserResource::getEloquentQuery())->count()))
            ->all();
    }
}
