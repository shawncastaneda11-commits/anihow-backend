<?php

namespace App\Filament\Resources\SellerRequests\Tables;

use App\Actions\Accounts\DecideSellerRequest;
use App\Enums\SellerRequestStatus;
use App\Filament\Resources\SellerRequests\SellerRequestResource;
use App\Models\SellerRequest;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class SellerRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at')
            ->columns([
                TextColumn::make('name')
                    ->label('Person')
                    ->weight(FontWeight::SemiBold)
                    ->description(fn (SellerRequest $record): string => collect([$record->email, $record->phone])->filter()->implode(' · '))
                    ->searchable(['name', 'email']),
                TextColumn::make('farm.name')
                    ->label('Farm')
                    ->visible(fn (): bool => SellerRequestResource::decides(auth()->user())),
                TextColumn::make('requester.name')
                    ->label('Asked by')
                    ->placeholder('—')
                    ->visible(fn (): bool => SellerRequestResource::decides(auth()->user())),
                TextColumn::make('note')
                    ->label('Note')
                    ->placeholder('—')
                    ->limit(60)
                    ->wrap(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (SellerRequestStatus $state): string => $state->label())
                    ->color(fn (SellerRequestStatus $state): string => match ($state) {
                        SellerRequestStatus::Waiting => 'warning',
                        SellerRequestStatus::Added => 'success',
                        SellerRequestStatus::NotAdded => 'danger',
                    })
                    ->description(fn (SellerRequest $record): ?string => $record->status === SellerRequestStatus::NotAdded
                        ? $record->decision_reason
                        : null)
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label('Sent')
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                // COSEL: create the account and hand over the temporary password.
                Action::make('addSeller')
                    ->label('Create the account')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (SellerRequest $record): string => 'Add '.$record->name.' to '.($record->farm?->name ?? 'the farm'))
                    ->modalDescription(fn (SellerRequest $record): string => self::checks($record))
                    ->modalSubmitActionLabel('Create the account')
                    ->visible(fn (SellerRequest $record): bool => $record->isWaiting() && SellerRequestResource::decides(auth()->user()))
                    ->action(function (SellerRequest $record, Action $action): void {
                        $cosel = auth()->user();
                        abort_unless($cosel instanceof User, 403);

                        try {
                            $result = app(DecideSellerRequest::class)->add($cosel, $record);
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title('The account was not created.')
                                ->body(collect($exception->errors())->flatten()->first())
                                ->danger()
                                ->send();

                            return;
                        }

                        $action->getLivewire()->replaceMountedAction('temporaryPassword', [
                            'password' => $result['password'],
                            'email' => $result['user']->email,
                        ]);
                    }),
                Action::make('declineSeller')
                    ->label("Don't add")
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->modalHeading(fn (SellerRequest $record): string => "Don't add {$record->name}")
                    ->modalDescription('The Content Editor sees your reason.')
                    ->visible(fn (SellerRequest $record): bool => $record->isWaiting() && SellerRequestResource::decides(auth()->user()))
                    ->schema([
                        Textarea::make('reason')
                            ->label('Reason')
                            ->required()
                            ->rows(2)
                            ->maxLength(500),
                    ])
                    ->action(function (SellerRequest $record, array $data): void {
                        $cosel = auth()->user();
                        abort_unless($cosel instanceof User, 403);

                        app(DecideSellerRequest::class)->decline($cosel, $record, (string) $data['reason']);
                    }),
            ])
            ->toolbarActions([]);
    }

    /**
     * What COSEL checks before creating the account.
     */
    private static function checks(SellerRequest $record): string
    {
        $taken = User::withTrashed()->where('email', $record->email)->exists();

        return collect([
            $taken ? "Warning: {$record->email} already has an AniHow account, so it cannot be added." : "{$record->email} has no AniHow account yet.",
            $record->farm?->is_active ? 'The farm is active.' : 'Warning: the farm is not active.',
            'The role is farmer-seller. A temporary password is shown to you once, to hand over in person.',
            $record->note ? 'Note from the Content Editor: '.$record->note : null,
        ])->filter()->implode(' ');
    }
}
