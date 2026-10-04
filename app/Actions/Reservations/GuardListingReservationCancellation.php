<?php

namespace App\Actions\Reservations;

use App\Enums\ReservationStatus;
use App\Exceptions\ReservationsRequireConfirmation;
use App\Models\Listing;
use Illuminate\Validation\ValidationException;

/**
 * One check for every path that takes a listing off the market. Active
 * reservations are not stock, so turning the listing off cancels them.
 */
class GuardListingReservationCancellation
{
    /**
     * @return array{count: int, quantity: float, unit: string}|null
     */
    public function describe(Listing $listing): ?array
    {
        $aggregate = $listing->reservations()
            ->where('status', ReservationStatus::Active)
            ->selectRaw('COUNT(*) as reservation_count, COALESCE(SUM(quantity), 0) as reserved_quantity')
            ->first();

        $count = (int) ($aggregate->reservation_count ?? 0);

        if ($count < 1) {
            return null;
        }

        return [
            'count' => $count,
            'quantity' => round((float) $aggregate->reserved_quantity, 2),
            'unit' => $listing->unit?->value ?? '',
        ];
    }

    public function warning(Listing $listing): ?string
    {
        $summary = $this->describe($listing);

        if ($summary === null) {
            return null;
        }

        $quantity = number_format($summary['quantity'], 2, '.', '');

        return "{$summary['count']} active reservation(s) ({$quantity} {$summary['unit']}) will be cancelled and the buyers notified. Restoring the listing later does NOT bring them back.";
    }

    public function conflictMessage(int $count, float $quantity, string $unit): string
    {
        $formatted = number_format($quantity, 2, '.', '');

        return "This listing has {$count} active reservation(s) ({$formatted} {$unit}). Turning it off cancels them and notifies the buyers.";
    }

    public function ensure(Listing $listing, bool $confirmed, bool $asValidationException = false): void
    {
        $summary = $this->describe($listing);

        if ($summary === null || $confirmed) {
            return;
        }

        $message = $this->conflictMessage($summary['count'], $summary['quantity'], $summary['unit']);

        if ($asValidationException) {
            throw ValidationException::withMessages([
                'confirm_cancel_reservations' => $message,
            ]);
        }

        throw new ReservationsRequireConfirmation(
            $summary['count'],
            $summary['quantity'],
            $message,
        );
    }
}
