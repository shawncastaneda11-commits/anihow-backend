<?php

namespace App\Filament\Resources\Users\Actions;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\TextSize;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Js;

class TemporaryPasswordAction
{
    public static function make(): Action
    {
        return Action::make('temporaryPassword')
            ->modalHeading('Temporary password')
            ->modalIcon(Heroicon::OutlinedKey)
            ->modalAlignment(Alignment::Center)
            ->modalFooterActionsAlignment(Alignment::Center)
            ->modalWidth(Width::Medium)
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->modalCloseButton(false)
            ->modalCancelAction(false)
            ->modalSubmitActionLabel('Done')
            ->schema(fn (array $arguments): array => self::fields($arguments))
            ->extraModalFooterActions(fn (array $arguments): array => [
                self::copyPasswordAction((string) ($arguments['password'] ?? '')),
            ])
            ->action(function (mixed $livewire): void {
                if ($livewire instanceof CreateUser) {
                    $livewire->finishTemporaryPassword();
                }
            });
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<int, TextEntry>
     */
    private static function fields(array $arguments): array
    {
        return [
            TextEntry::make('password')
                ->hiddenLabel()
                ->state((string) ($arguments['password'] ?? ''))
                ->alignCenter()
                ->fontFamily(FontFamily::Mono)
                ->size(TextSize::Large)
                ->copyable()
                ->copyMessage('Copied'),
            TextEntry::make('email')
                ->hiddenLabel()
                ->state((string) ($arguments['email'] ?? ''))
                ->alignCenter(),
            TextEntry::make('guidance')
                ->hiddenLabel()
                ->state(User::temporaryPasswordGuidance())
                ->alignCenter(),
        ];
    }

    private static function copyPasswordAction(string $password): Action
    {
        $passwordJs = Js::from($password);
        $copiedJs = Js::from('Copied');

        return Action::make('copyPassword')
            ->label('Copy password')
            ->icon(Heroicon::OutlinedClipboard)
            ->color('gray')
            ->livewireClickHandlerEnabled(false)
            ->alpineClickHandler(<<<JS
                window.navigator.clipboard.writeText({$passwordJs})
                \$tooltip({$copiedJs}, {
                    theme: \$store.theme,
                    timeout: 2000,
                })
                JS);
    }
}
