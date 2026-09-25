<?php

namespace App\Filament\Resources\AccountDeletionRequests\Tables;

use App\Actions\Privacy\ApproveAccountDeletionRequestAction;
use App\Actions\Privacy\RejectAccountDeletionRequestAction;
use App\Enums\AccountDeletionStatus;
use App\Models\AccountDeletionRequest;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AccountDeletionRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (AccountDeletionStatus $state): string => $state->label())
                    ->color(fn (AccountDeletionStatus $state): string => match ($state) {
                        AccountDeletionStatus::Pending => 'warning',
                        AccountDeletionStatus::Completed => 'success',
                        AccountDeletionStatus::Rejected => 'gray',
                    }),
                TextColumn::make('user.name')
                    ->label('Requested by')
                    ->searchable(),
                TextColumn::make('user.email')
                    ->label('Email')
                    ->toggleable(),
                TextColumn::make('reason')
                    ->limit(40)
                    ->wrap()
                    ->placeholder('—'),
                TextColumn::make('processedBy.name')
                    ->label('Processed by')
                    ->placeholder('Open')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(AccountDeletionStatus::options()),
            ])
            ->recordActions([
                Action::make('approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve account deletion')
                    ->modalDescription('This anonymises the account. Order records stay. The person is emailed at their current address first.')
                    ->visible(fn (AccountDeletionRequest $record): bool => $record->isPending())
                    ->action(fn (AccountDeletionRequest $record) => app(ApproveAccountDeletionRequestAction::class)
                        ->handle(auth()->user(), $record)),
                Action::make('reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->visible(fn (AccountDeletionRequest $record): bool => $record->isPending())
                    ->schema([
                        Textarea::make('rejection_note')
                            ->label('Why this request is rejected')
                            ->required()
                            ->rows(2),
                    ])
                    ->action(fn (AccountDeletionRequest $record, array $data) => app(RejectAccountDeletionRequestAction::class)
                        ->handle(auth()->user(), $record, $data['rejection_note'])),
            ])
            ->toolbarActions([]);
    }
}
