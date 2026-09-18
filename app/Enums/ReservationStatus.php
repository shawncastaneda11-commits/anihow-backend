<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case Pending = 'pending';
    case ReadyForPickup = 'ready_for_pickup';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::ReadyForPickup => 'Ready for pickup',
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
            self::Pending => [self::ReadyForPickup, self::Cancelled],
            self::ReadyForPickup => [self::Completed, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }
}
