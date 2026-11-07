<?php

namespace App\Filament\Resources\FarmAnnouncements\Pages;

use App\Actions\Announcements\SendSystemAnnouncementAction;
use App\Enums\Permission;
use App\Filament\Resources\FarmAnnouncements\FarmAnnouncementResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListFarmAnnouncements extends ListRecords
{
    protected static string $resource = FarmAnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // COSEL only: a message to the whole system rather than one farm.
            Action::make('announceToEveryone')
                ->label('Announce to everyone')
                ->icon('heroicon-o-megaphone')
                ->color('gray')
                ->visible(fn (): bool => auth()->user()?->can(Permission::ManageFarms->value) ?? false)
                ->modalDescription('Sent now as an in-app notification. It cannot be unsent.')
                ->schema([
                    Select::make('audience')
                        ->options(SendSystemAnnouncementAction::AUDIENCES)
                        ->default('everyone')
                        ->required()
                        ->native(false),
                    TextInput::make('title')->required()->maxLength(100),
                    Textarea::make('body')->label('Message')->required()->rows(4)->maxLength(1000),
                ])
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    $sender = auth()->user();
                    abort_unless($sender instanceof User && $sender->can(Permission::ManageFarms->value), 403);

                    $sent = app(SendSystemAnnouncementAction::class)
                        ->handle($sender, $data['audience'], $data['title'], $data['body']);

                    Notification::make()->title("Sent to {$sent} people.")->success()->send();
                }),
            CreateAction::make(),
        ];
    }
}
