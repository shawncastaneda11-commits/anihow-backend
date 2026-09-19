<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Placed = 'placed';
    case Confirmed = 'confirmed';
    case Ready = 'ready';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Placed => 'Placed',
            self::Confirmed => 'Confirmed',
            self::Ready => 'Ready',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    /**
     * @return list<self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Placed => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Ready, self::Cancelled],
            self::Ready => [self::Completed, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    /**
     * Stock is held from Placed and deducted at Confirmed. A status at or past
     * Confirmed has already taken the quantity out of quantity_available.
     */
    public function hasDeductedStock(): bool
    {
        return in_array($this, [self::Confirmed, self::Ready, self::Completed], true);
    }

    /**
     * Before Confirmed, the quantity sits in quantity_held and is released on
     * cancellation. After Confirmed, cancellation restores quantity_available.
     */
    public function holdsStock(): bool
    {
        return $this === self::Placed;
    }

    /**
     * Prices and tawad amounts are frozen once an order reaches Confirmed.
     */
    public function isPriceLocked(): bool
    {
        return $this !== self::Placed;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])
            ->all();
    }
}
