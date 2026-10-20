<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\FulfillmentPreference;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use BackedEnum;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

/**
 * Read-only. Used by the view action on the orders table.
 */
class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('order_number')->disabled(),
                Placeholder::make('from_reservation')
                    ->label('From reservation')
                    ->content('Yes')
                    ->visible(fn (?Order $record): bool => $record?->reservation_id !== null),
                TextInput::make('status')
                    ->formatStateUsing(fn (mixed $state): string => self::enumLabel(OrderStatus::class, $state))
                    ->disabled(),
                TextInput::make('buyer.name')->label('Buyer')->disabled(),
                TextInput::make('farmerSeller.name')->label('Seller')->disabled(),
                TextInput::make('farm.name')->label('Farm')->disabled(),
                TextInput::make('fulfillment_preference')
                    ->label('Fulfillment')
                    ->formatStateUsing(fn (mixed $state): string => self::enumLabel(FulfillmentPreference::class, $state))
                    ->disabled(),
                Textarea::make('fulfillment_note')
                    ->label('Arrangement note')
                    ->rows(2)
                    ->disabled()
                    ->columnSpanFull(),
                TextInput::make('payment_method')
                    ->label('Payment')
                    ->formatStateUsing(fn (mixed $state): string => self::enumLabel(PaymentMethod::class, $state))
                    ->disabled(),
                TextInput::make('subtotal')->prefix('PHP')->disabled(),
                TextInput::make('tawad_total')->label('Tawad')->prefix('PHP')->disabled(),
                TextInput::make('total')->prefix('PHP')->disabled(),
                TextInput::make('amount_received')
                    ->label(function (?Order $record): string {
                        $value = $record?->paymentMethodValue();

                        return $value === PaymentMethod::OnlineTransfer->value
                            ? 'Amount received (online)'
                            : 'Cash received at handover';
                    })
                    ->prefix('PHP')
                    ->disabled(),
                Textarea::make('cancellation_note')
                    ->rows(2)
                    ->disabled()
                    ->visible(fn (?Order $record): bool => filled($record?->cancellation_note))
                    ->columnSpanFull(),
                Placeholder::make('order_chat')
                    ->label('Order chat')
                    ->content(fn (?Order $record): HtmlString => self::chatContent($record))
                    ->columnSpanFull(),
            ]);
    }

    /**
     * The form state holds the enum on the first render and its plain value
     * after Livewire round-trips it, so accept either.
     *
     * @param  class-string<OrderStatus|FulfillmentPreference|PaymentMethod>  $enum
     */
    private static function enumLabel(string $enum, mixed $state): string
    {
        if ($state instanceof $enum) {
            return $state->label();
        }

        $value = $state instanceof BackedEnum ? (string) $state->value : (string) $state;

        return $enum::tryFrom($value)?->label() ?? $value;
    }

    private static function chatContent(?Order $record): HtmlString
    {
        if ($record === null) {
            return new HtmlString('<span class="text-sm text-gray-500">No order selected.</span>');
        }

        if ($record->isWalkIn()) {
            return new HtmlString(
                '<span class="text-sm text-gray-500">Walk-in sales have no buyer chat.</span>',
            );
        }

        $record->loadMissing('stallMessages.author');

        if ($record->stallMessages->isEmpty()) {
            return new HtmlString(
                '<span class="text-sm text-gray-500">No messages yet.</span>',
            );
        }

        $lines = $record->stallMessages->map(function ($message): string {
            $author = e($message->author?->name ?? 'Unknown');
            $when = e(optional($message->created_at)?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '');
            $body = nl2br(e((string) $message->body));
            $file = filled($message->attachment_name)
                ? '<div class="text-sm text-gray-500">'.e($message->attachment_name).'</div>'
                : '';

            return "<div class=\"mb-3\"><div class=\"text-sm font-medium\">{$author} <span class=\"font-normal text-gray-500\">{$when}</span></div><div class=\"text-sm\">{$body}</div>{$file}</div>";
        })->implode('');

        return new HtmlString($lines);
    }
}
