<?php

namespace App\Filament\Resources\SellerRequests\Pages;

use App\Actions\Accounts\RequestFarmerSellers;
use App\Enums\SellerRequestStatus;
use App\Filament\Resources\SellerRequests\SellerRequestResource;
use App\Filament\Resources\Users\Actions\TemporaryPasswordAction;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;

class ListSellerRequests extends ListRecords
{
    protected static string $resource = SellerRequestResource::class;

    public function getSubheading(): ?string
    {
        return SellerRequestResource::decides(auth()->user())
            ? 'Content Editors ask for new farmer-sellers on their farm; you add them or say why not. Oldest first.'
            : 'To add someone to your farm, send a request. COSEL creates the account and hands them a temporary password.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('askCosel')
                ->label('Ask COSEL to add farmer-sellers')
                ->icon('heroicon-o-user-plus')
                ->visible(fn (): bool => SellerRequestResource::asks(auth()->user()))
                ->modalDescription(fn (): string => 'For '.(auth()->user()?->farm?->name ?? 'your farm').' only. COSEL creates the accounts and hands each person a temporary password.')
                ->modalSubmitActionLabel('Send to COSEL')
                ->fillForm(['mode' => 'one'])
                ->schema([
                    Radio::make('mode')
                        ->label('How many')
                        ->options(['one' => 'One person', 'many' => 'Many at once'])
                        ->inline()
                        ->live(),
                    TextInput::make('name')
                        ->label('Full name')
                        ->maxLength(255)
                        ->visible(fn (Get $get): bool => $get('mode') === 'one')
                        ->required(fn (Get $get): bool => $get('mode') === 'one'),
                    TextInput::make('email')
                        ->email()
                        ->maxLength(255)
                        ->visible(fn (Get $get): bool => $get('mode') === 'one')
                        ->required(fn (Get $get): bool => $get('mode') === 'one'),
                    TextInput::make('phone')
                        ->label('Mobile number (optional)')
                        ->maxLength(30)
                        ->visible(fn (Get $get): bool => $get('mode') === 'one'),
                    Textarea::make('rows')
                        ->label('One person per line: name, email, mobile')
                        ->rows(6)
                        ->placeholder("Maria Santos, maria@example.com\nPedro Reyes, pedro@example.com, 09171234567")
                        ->visible(fn (Get $get): bool => $get('mode') === 'many')
                        ->required(fn (Get $get): bool => $get('mode') === 'many'),
                    Textarea::make('note')
                        ->label('Note for COSEL (optional)')
                        ->rows(2)
                        ->maxLength(500),
                ])
                ->action(function (array $data): void {
                    $editor = auth()->user();
                    abort_unless($editor instanceof User && SellerRequestResource::asks($editor), 403);

                    $rows = ($data['mode'] ?? 'one') === 'many'
                        ? (string) ($data['rows'] ?? '')
                        : implode(', ', [
                            str_replace(',', ' ', (string) ($data['name'] ?? '')),
                            (string) ($data['email'] ?? ''),
                            (string) ($data['phone'] ?? ''),
                        ]);

                    $result = app(RequestFarmerSellers::class)->handle($editor, $rows, $data['note'] ?? null);
                    $sent = count($result['sent']);

                    $notification = Notification::make()
                        ->title($sent === 1 ? 'Sent to COSEL.' : "{$sent} requests sent to COSEL.")
                        ->body($result['skipped'] === [] ? 'COSEL sees it on their home screen.' : 'Not sent: '.implode(' ', $result['skipped']))
                        ->persistent();

                    ($result['skipped'] === [] ? $notification->success() : $notification->warning())->send();
                }),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'waiting' => Tab::make('Waiting')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', SellerRequestStatus::Waiting))
                ->badge(fn (): int => SellerRequestResource::getEloquentQuery()->where('status', SellerRequestStatus::Waiting)->count()),
            'decided' => Tab::make('Decided')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', '!=', SellerRequestStatus::Waiting)),
            'all' => Tab::make('All'),
        ];
    }

    public function temporaryPasswordAction(): Action
    {
        return TemporaryPasswordAction::make();
    }
}
