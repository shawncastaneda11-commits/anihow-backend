<?php

namespace App\Support;

use App\Enums\NotificationType;
use App\Mail\ListingLowStockMail;
use App\Mail\ReservationCreatedMail;
use App\Mail\ReservationStatusChangedMail;
use App\Models\InAppNotification;
use App\Models\Listing;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;

class InAppNotifier
{
    /**
     * Persist an in-app notification, then queue email as an additional channel.
     *
     * TODO(push-notifications): no push provider yet. Email is queued; in-app
     * records stay the source of truth for the mobile inbox.
     */
    public function send(
        User $user,
        NotificationType $type,
        string $title,
        string $body,
        ?Model $related = null,
    ): InAppNotification {
        $notification = $user->inAppNotifications()->create([
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'related_id' => $related?->getKey(),
            'related_type' => $related?->getMorphClass(),
        ]);

        $this->queueEmail($user, $type, $related);

        return $notification;
    }

    public function reservationCreated(User $farmer, Reservation $reservation): InAppNotification
    {
        $buyerName = $reservation->buyer?->name ?? 'A buyer';

        return $this->send(
            $farmer,
            NotificationType::ReservationCreated,
            'New reservation',
            "{$buyerName} reserved produce totaling {$reservation->total}.",
            $reservation,
        );
    }

    public function reservationStatusChanged(User $buyer, Reservation $reservation): InAppNotification
    {
        $status = $reservation->status?->label() ?? $reservation->status?->value;

        return $this->send(
            $buyer,
            NotificationType::ReservationStatusChanged,
            'Reservation updated',
            "Your reservation is now {$status}.",
            $reservation,
        );
    }

    public function listingLowStock(User $farmer, Listing $listing): InAppNotification
    {
        $unit = $listing->unit?->value ?? '';

        return $this->send(
            $farmer,
            NotificationType::ListingLowStock,
            'Low stock',
            "{$listing->name} is down to {$listing->quantity_available} {$unit}.",
            $listing,
        );
    }

    private function queueEmail(User $user, NotificationType $type, ?Model $related): void
    {
        if (! $user->hasVerifiedEmail() || $related === null) {
            return;
        }

        $mailable = match ($type) {
            NotificationType::ReservationCreated => $related instanceof Reservation
                ? new ReservationCreatedMail($related)
                : null,
            NotificationType::ReservationStatusChanged => $related instanceof Reservation
                ? new ReservationStatusChangedMail($related)
                : null,
            NotificationType::ListingLowStock => $related instanceof Listing
                ? new ListingLowStockMail($related)
                : null,
        };

        if ($mailable) {
            Mail::to($user)->queue($mailable);
        }
    }
}
