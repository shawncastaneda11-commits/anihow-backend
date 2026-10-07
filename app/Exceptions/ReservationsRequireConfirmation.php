<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A seller or moderator tried to take a listing off the market while buyers
 * still hold active reservations, and did not confirm that those reservations
 * will be cancelled.
 */
class ReservationsRequireConfirmation extends RuntimeException
{
    public function __construct(
        public readonly int $activeReservationsCount,
        public readonly float $reservedQuantity,
        string $message,
    ) {
        parent::__construct($message);
    }
}
