<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Str;

/**
 * Human readable and quoted out loud at handover, so it avoids characters that
 * are easy to mishear: no 0/O, no 1/I.
 */
class OrderNumberGenerator
{
    private const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public function generate(): string
    {
        do {
            $suffix = collect(range(1, 5))
                ->map(fn (): string => self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)])
                ->implode('');

            $number = 'AH-'.now()->format('ymd').'-'.$suffix;
        } while (Order::query()->where('order_number', $number)->exists());

        return $number;
    }
}
