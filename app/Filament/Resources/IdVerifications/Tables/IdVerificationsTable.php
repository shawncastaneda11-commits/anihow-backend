<?php

namespace App\Filament\Resources\IdVerifications\Tables;

use App\Actions\IdVerification\ReviewIdVerificationAction;
use App\Enums\IdDocumentType;
use App\Enums\IdVerificationStatus;
use App\Models\IdVerification;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Width;
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
                    ->visible(fn (mixed $livewire): bool => ($livewire->activeTab ?? null) !== 'waiting')
                    ->formatStateUsing(fn (IdVerificationStatus $state): string => $state->label())
                    ->color(fn (IdVerificationStatus $state): string => match ($state) {
                        IdVerificationStatus::Pending => 'warning',
                        IdVerificationStatus::Approved => 'success',
                        IdVerificationStatus::Rejected => 'gray',
                    }),
                TextColumn::make('user.name')
                    ->label('Buyer')
                    ->weight(FontWeight::SemiBold)
                    ->description(fn (IdVerification $record): ?string => $record->user?->email)
                    ->searchable(),
                TextColumn::make('user.email')
                    ->label('Email')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('id_type')
                    ->label('ID type')
                    ->formatStateUsing(fn (IdDocumentType $state): string => $state->label()),
                // Empty while an ID waits, so shown only once decided.
                TextColumn::make('rejection_reason')
                    ->label('Reason')
                    ->limit(40)
                    ->wrap()
                    ->placeholder('—')
                    ->toggleable()
                    ->visible(fn (mixed $livewire): bool => ($livewire->activeTab ?? null) !== 'waiting'),
                TextColumn::make('reviewer.name')
                    ->label('Reviewed by')
                    ->placeholder('Open')
                    ->toggleable()
                    ->visible(fn (mixed $livewire): bool => ($livewire->activeTab ?? null) !== 'waiting'),
                TextColumn::make('created_at')
                    ->label('Sent')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(IdVerificationStatus::options()),
            ])
            // Clicking a waiting ID opens the review panel.
            ->recordAction(fn (IdVerification $record): ?string => $record->isPending() ? 'review' : null)
            // One "⋯" menu per row; clicking the row opens its panel.
            ->recordActions([
                ActionGroup::make([
                    self::review(),
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
                ])->tooltip('More'),
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

    /**
     * The review panel: both photos side by side, the details to check, and
     * one decision. Approving and rejecting work as the separate buttons do.
     */
    private static function review(): Action
    {
        return Action::make('review')
            ->label('Review')
            ->icon('heroicon-o-identification')
            ->slideOver()
            ->modalWidth(Width::FourExtraLarge)
            ->visible(fn (IdVerification $record): bool => $record->isPending())
            ->modalHeading(fn (IdVerification $record): string => (string) $record->user?->name)
            ->modalDescription(fn (IdVerification $record): string => collect([
                $record->user?->email,
                'sent '.$record->created_at?->diffForHumans(),
                self::earlierTries($record),
            ])->filter()->implode(' · '))
            ->modalContent(fn (IdVerification $record): HtmlString => self::photos($record, sideBySide: true))
            ->fillForm(['decision' => 'approve'])
            ->schema([
                Radio::make('decision')
                    ->label('Your decision')
                    ->options(['approve' => 'Approve', 'reject' => 'Reject'])
                    ->descriptions([
                        'approve' => 'The name matches, the whole card shows, it is not expired, and the selfie matches.',
                        'reject' => 'The buyer sees the reason and can send a new photo.',
                    ])
                    ->required()
                    ->live(),
                Select::make('reason')
                    ->label('Reason')
                    ->options(self::REJECTION_REASONS)
                    ->visible(fn (Get $get): bool => $get('decision') === 'reject')
                    ->required(fn (Get $get): bool => $get('decision') === 'reject')
                    ->live(),
                Textarea::make('note')
                    ->label('Note for the buyer')
                    ->rows(2)
                    ->maxLength(300)
                    ->visible(fn (Get $get): bool => $get('decision') === 'reject')
                    ->required(fn (Get $get): bool => $get('decision') === 'reject' && $get('reason') === 'other'),
                Text::make('The photos are deleted once you decide.')->color('gray'),
            ])
            ->modalSubmitActionLabel('Save decision')
            ->action(function (IdVerification $record, array $data): void {
                $review = app(ReviewIdVerificationAction::class);

                if (($data['decision'] ?? 'approve') === 'reject') {
                    $review->reject(auth()->user(), $record, self::rejectionText($data));

                    return;
                }

                $review->approve(auth()->user(), $record);
            });
    }

    private static function earlierTries(IdVerification $record): string
    {
        $rejected = IdVerification::query()
            ->where('user_id', $record->user_id)
            ->whereKeyNot($record->getKey())
            ->where('status', IdVerificationStatus::Rejected)
            ->count();

        return $rejected === 0 ? 'first try' : $rejected.' earlier '.($rejected === 1 ? 'try' : 'tries').' rejected';
    }

    private static function photos(IdVerification $record, bool $sideBySide = false): HtmlString
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

        $gallery = $images !== ''
            ? ($sideBySide ? '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(16rem,1fr));gap:14px">'.$images.'</div>' : $images)
            : '<p class="text-sm text-gray-500">The photos are no longer available.</p>';

        return new HtmlString(
            '<div style="display:flex;flex-direction:column;gap:12px">'
            .'<p class="text-sm">Account name: <strong>'.$name.'</strong> · '.$type.'</p>'
            .$gallery
            .'</div>'
        );
    }
}
