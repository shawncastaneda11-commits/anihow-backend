<?php

namespace App\Filament\Resources\Listings\Actions;

use App\Actions\Listings\EditFarmListing;
use App\Enums\GrowingMethod;
use App\Enums\ListingStatus;
use App\Enums\ListingUnit;
use App\Enums\TawadType;
use App\Models\Listing;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Validation\ValidationException;

/**
 * A Content Editor's edits to their farm's listings: everything the seller can
 * change in the app except photos, plus the tawad. Each goes through
 * EditFarmListing, so the app's own checks apply and the seller is told.
 */
class FarmListingActions
{
    /**
     * @return list<Action>
     */
    public static function make(): array
    {
        return [self::edit(), self::setTawad(), self::endTawad()];
    }

    private static function canEdit(Listing $record): bool
    {
        return $record->status !== ListingStatus::TakenDown
            && $record->farmer_seller_id !== auth()->id()
            && (auth()->user()?->can('update', $record) ?? false)
            && (auth()->user()?->farm_id !== null);
    }

    private static function edit(): Action
    {
        return Action::make('editFarmListing')
            ->label('Edit')
            ->icon('heroicon-o-pencil-square')
            ->visible(fn (Listing $record): bool => self::canEdit($record))
            ->modalHeading(fn (Listing $record): string => 'Edit '.$record->title)
            ->modalDescription('The seller is notified of what you change. Orders already placed keep their price.')
            ->fillForm(fn (Listing $record): array => [
                'title' => $record->title,
                'description' => $record->description,
                'unit' => $record->unit?->value,
                'price_per_unit' => $record->price_per_unit,
                'quantity_available' => $record->quantity_available,
                'min_order_quantity' => $record->min_order_quantity,
                'order_step' => $record->order_step,
                'is_active' => (bool) $record->is_active,
                'available_from' => $record->available_from?->toDateTimeString(),
                'available_until' => $record->available_until?->toDateTimeString(),
                'harvested_on' => $record->harvested_on?->toDateString(),
                'growing_method' => $record->growing_method?->value,
            ])
            ->schema([
                TextInput::make('title')->required()->maxLength(150),
                Textarea::make('description')->rows(3)->maxLength(5000),
                Grid::make(3)->schema([
                    Select::make('unit')->options(ListingUnit::options())->required()->native(false),
                    TextInput::make('price_per_unit')->label('Price per unit (PHP)')->numeric()->required()->prefix('PHP'),
                    TextInput::make('quantity_available')->label('Stock')->numeric()->required(),
                    TextInput::make('min_order_quantity')->label('Minimum order')->numeric()->required(),
                    TextInput::make('order_step')->label('Order step')->numeric()->required(),
                    Select::make('growing_method')->options(GrowingMethod::options())->native(false),
                ]),
                Grid::make(3)->schema([
                    DateTimePicker::make('available_from')->seconds(false),
                    DateTimePicker::make('available_until')->seconds(false),
                    DatePicker::make('harvested_on'),
                ]),
                Toggle::make('is_active')->label('Shown to buyers')->live(),
                Checkbox::make('confirm_cancel_reservations')
                    ->label('If this listing has reservations, cancel them')
                    ->visible(fn (Get $get): bool => ! $get('is_active')),
            ])
            ->action(function (Listing $record, array $data, Action $action): void {
                $confirm = (bool) ($data['confirm_cancel_reservations'] ?? false);
                unset($data['confirm_cancel_reservations']);

                self::inModal($action, fn () => app(EditFarmListing::class)->update(self::editor(), $record, $data, $confirm));

                Notification::make()->title('Listing updated. The seller has been notified.')->success()->send();
            });
    }

    private static function setTawad(): Action
    {
        return Action::make('setFarmTawad')
            ->label('Tawad')
            ->icon('heroicon-o-tag')
            ->visible(fn (Listing $record): bool => self::canEdit($record))
            ->modalHeading(fn (Listing $record): string => 'Tawad for '.$record->title)
            ->modalDescription('Saving replaces the current tawad. The same limits as in the app apply, per unit.')
            ->fillForm(fn (Listing $record): array => [
                'type' => $record->activeTawadRule?->type?->value ?? TawadType::Flat->value,
                'discount_amount' => $record->activeTawadRule?->discount_amount,
                'min_quantity' => $record->activeTawadRule?->min_quantity,
            ])
            ->schema([
                Select::make('type')
                    ->label('Who gets the tawad')
                    ->options([
                        TawadType::Flat->value => 'Every order',
                        TawadType::MinimumQuantity->value => 'Bulk orders (at a minimum quantity)',
                    ])
                    ->required()
                    ->native(false)
                    ->live(),
                TextInput::make('discount_amount')->label('Peso off')->numeric()->required()->prefix('PHP'),
                TextInput::make('min_quantity')
                    ->label('When they buy at least')
                    ->numeric()
                    ->visible(fn (Get $get): bool => $get('type') === TawadType::MinimumQuantity->value)
                    ->required(fn (Get $get): bool => $get('type') === TawadType::MinimumQuantity->value),
            ])
            ->action(function (Listing $record, array $data, Action $action): void {
                self::inModal($action, fn () => app(EditFarmListing::class)->setTawad(self::editor(), $record, $data));

                Notification::make()->title('Tawad saved. The seller has been notified.')->success()->send();
            });
    }

    private static function endTawad(): Action
    {
        return Action::make('endFarmTawad')
            ->label('End tawad')
            ->icon('heroicon-o-x-circle')
            ->color('gray')
            ->requiresConfirmation()
            ->visible(fn (Listing $record): bool => self::canEdit($record) && $record->activeTawadRule !== null)
            ->action(function (Listing $record): void {
                app(EditFarmListing::class)->endTawad(self::editor(), $record);

                Notification::make()->title('Tawad ended. The seller has been notified.')->success()->send();
            });
    }

    private static function editor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    /**
     * Shows a refusal under the field it is about, inside the open modal.
     */
    private static function inModal(Action $action, Closure $run): void
    {
        try {
            $run();
        } catch (ValidationException $exception) {
            $statePath = 'mountedActions.'.($action->getNestingIndex() ?? 0).'.data.';

            throw ValidationException::withMessages(
                collect($exception->errors())
                    ->mapWithKeys(fn (array $messages, string $field): array => [$statePath.$field => $messages])
                    ->all(),
            );
        }
    }
}
