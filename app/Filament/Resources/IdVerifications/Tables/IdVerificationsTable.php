<?php

namespace App\Filament\Resources\IdVerifications\Tables;

use App\Actions\IdVerification\ReviewIdVerificationAction;
use App\Enums\IdDocumentType;
use App\Enums\IdVerificationStatus;
use App\Models\IdVerification;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class IdVerificationsTable
{
    /**
     * Reasons a buyer sees in the app when their ID is not accepted.
     *
     * @var array<string, string>
     */
    public const REJECTION_REASONS = [
        'blurry' => 'The photo is blurry and the details cannot be read. Please take a clearer photo.',
        'cropped' => 'Part of the ID is cut off. Please show the whole card.',
        'name_mismatch' => 'The name on the ID does not match your account name.',
        'expired' => 'The ID has expired. Please use a valid ID.',
        'not_government' => 'This is not a government-issued ID.',
        'selfie_mismatch' => 'The selfie does not match the photo on the ID.',
        'other' => 'Other (write the reason below)',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (IdVerificationStatus $state): string => $state->label())
                    ->color(fn (IdVerificationStatus $state): string => match ($state) {
                        IdVerificationStatus::Pending => 'warning',
                        IdVerificationStatus::Approved => 'success',
                        IdVerificationStatus::Rejected => 'gray',
                    }),
                TextColumn::make('user.name')
                    ->label('Buyer')
                    ->searchable(),
                TextColumn::make('user.email')
                    ->label('Email')
                    ->toggleable(),
                TextColumn::make('id_type')
                    ->label('ID type')
                    ->formatStateUsing(fn (IdDocumentType $state): string => $state->label()),
                TextColumn::make('rejection_reason')
                    ->label('Reason')
                    ->limit(40)
                    ->wrap()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('reviewer.name')
                    ->label('Reviewed by')
                    ->placeholder('Open')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(IdVerificationStatus::options()),
            ])
            ->recordActions([
                Action::make('approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve this ID')
                    ->modalDescription('Check that the ID is real, readable and matches the account name. The photos are deleted once you decide.')
                    ->modalContent(fn (IdVerification $record): HtmlString => self::photos($record))
                    ->visible(fn (IdVerification $record): bool => $record->isPending())
                    ->action(fn (IdVerification $record) => app(ReviewIdVerificationAction::class)
                        ->approve(auth()->user(), $record)),
                Action::make('reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->modalHeading('Reject this ID')
                    ->modalDescription('The buyer sees the reason and can submit a new photo. The photos are deleted once you decide.')
                    ->modalContent(fn (IdVerification $record): HtmlString => self::photos($record))
                    ->visible(fn (IdVerification $record): bool => $record->isPending())
                    ->schema([
                        Select::make('reason')
                            ->label('Reason')
                            ->options(self::REJECTION_REASONS)
                            ->required()
                            ->live(),
                        Textarea::make('note')
                            ->label('Reason shown to the buyer')
                            ->rows(2)
                            ->maxLength(300)
                            ->required(fn (Get $get): bool => $get('reason') === 'other'),
                    ])
                    ->action(fn (IdVerification $record, array $data) => app(ReviewIdVerificationAction::class)
                        ->reject(auth()->user(), $record, self::rejectionText($data))),
            ])
            ->toolbarActions([]);
    }

    /**
     * @param  array{reason?: string|null, note?: string|null}  $data
     */
    public static function rejectionText(array $data): string
    {
        $note = trim((string) ($data['note'] ?? ''));
        $reason = $data['reason'] ?? 'other';

        if ($reason === 'other' || ! isset(self::REJECTION_REASONS[$reason])) {
            return $note;
        }

        return $note === '' ? self::REJECTION_REASONS[$reason] : self::REJECTION_REASONS[$reason].' '.$note;
    }

    private static function photos(IdVerification $record): HtmlString
    {
        $images = '';

        foreach (['front' => 'Front of ID', 'selfie' => 'Selfie with ID'] as $side => $label) {
            $path = $side === 'front' ? $record->front_path : $record->selfie_path;

            if (! filled($path)) {
                continue;
            }

            $url = e(route('id-verifications.photo', ['idVerification' => $record, 'side' => $side]));
            $images .= '<figure style="margin:0"><figcaption class="text-sm font-medium" style="margin-bottom:4px">'.e($label).'</figcaption>'
                .'<a href="'.$url.'" target="_blank" rel="noopener"><img src="'.$url.'" alt="'.e($label).'" style="width:100%;max-height:320px;object-fit:contain;border-radius:8px;background:#0001"></a></figure>';
        }

        $name = e((string) $record->user?->name);
        $type = e($record->id_type->label());

        return new HtmlString(
            '<div style="display:flex;flex-direction:column;gap:12px">'
            .'<p class="text-sm">Account name: <strong>'.$name.'</strong> · '.$type.'</p>'
            .($images !== '' ? $images : '<p class="text-sm text-gray-500">The photos are no longer available.</p>')
            .'</div>'
        );
    }
}
