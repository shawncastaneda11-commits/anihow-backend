<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
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
                TextInput::make('status')
                    ->formatStateUsing(fn ($state): string => $state?->label() ?? '')
                    ->disabled(),
                TextInput::make('buyer.name')->label('Buyer')->disabled(),
                TextInput::make('farmerSeller.name')->label('Seller')->disabled(),
                TextInput::make('farm.name')->label('Farm')->disabled(),
                TextInput::make('fulfillment_preference')
                    ->label('Fulfillment')
                    ->formatStateUsing(fn ($state): string => $state?->label() ?? '')
                    ->disabled(),
                Textarea::make('fulfillment_note')
                    ->label('Arrangement note')
                    ->rows(2)
                    ->disabled()
                    ->columnSpanFull(),
                TextInput::make('subtotal')->prefix('PHP')->disabled(),
                TextInput::make('tawad_total')->label('Tawad')->prefix('PHP')->disabled(),
                TextInput::make('total')->prefix('PHP')->disabled(),
                TextInput::make('amount_received')
                    ->label('Cash received at handover')
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

        $record->loadMissing('messages.author');

        if ($record->messages->isEmpty()) {
            return new HtmlString(
                '<span class="text-sm text-gray-500">No messages yet.</span>',
            );
        }

        $lines = $record->messages->map(function ($message): string {
            $author = e($message->author?->name ?? 'Unknown');
            $when = e(optional($message->created_at)?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '');
            $body = nl2br(e($message->body));

            return "<div class=\"mb-3\"><div class=\"text-sm font-medium\">{$author} <span class=\"font-normal text-gray-500\">{$when}</span></div><div class=\"text-sm\">{$body}</div></div>";
        })->implode('');

        return new HtmlString($lines);
    }
}
