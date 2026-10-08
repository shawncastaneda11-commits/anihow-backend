<?php

namespace App\Support;

use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use App\Mail\ListingLowStockMail;
use App\Models\AccountDeletionRequest;
use App\Models\Farm;
use App\Models\FarmAnnouncement;
use App\Models\InAppNotification;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Report;
use App\Models\Reservation;
use App\Models\User;
use App\Support\Pricing\UnitConverter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Throwable;

class InAppNotifier
{
    /**
     * Persist an in-app notification, then queue email as an additional channel.
     *
     * TODO(push-notifications): no push provider yet.
     * TODO(order-emails): OrderPlacedMail and OrderStatusChangedMail replace
     * the deleted reservation mailables and still need their blade views. In-app
     * records stay the source of truth for the mobile inbox, so order email is
     * off until those exist rather than half-wired.
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

    /**
     * A new order reaches the farmer-seller.
     */
    public function orderPlaced(User $farmer, Order $order): InAppNotification
    {
        $buyerName = $order->buyer?->name ?? 'A buyer';
        $total = number_format((float) $order->total, 2, '.', '');

        return $this->send(
            $farmer,
            NotificationType::OrderPlaced,
            NotificationType::OrderPlaced->label(),
            "{$buyerName} placed an order totaling PHP {$total}.",
            $order,
        );
    }

    /**
     * A placed order is still waiting for the farmer-seller to confirm.
     */
    public function orderAwaitingConfirmation(User $farmer, Order $order): InAppNotification
    {
        return $this->send(
            $farmer,
            NotificationType::OrderAwaitingConfirmation,
            NotificationType::OrderAwaitingConfirmation->label(),
            'An order is still waiting for your confirmation.',
            $order,
        );
    }

    /**
     * Every state after Placed reaches the buyer.
     */
    public function orderStatusChanged(User $recipient, Order $order, OrderStatus $status): ?InAppNotification
    {
        $type = NotificationType::forOrderStatus($status);

        if ($type === null) {
            return null;
        }

        return $this->send(
            $recipient,
            $type,
            $type->label(),
            "This order is now {$status->label()}.",
            $order,
        );
    }

    public function listingLowStock(User $farmer, Listing $listing): InAppNotification
    {
        $unit = $listing->unit?->value ?? $listing->cropType?->unit_of_measure?->value ?? '';
        $quantity = number_format($listing->sellableQuantity(), 2, '.', '');

        return $this->send(
            $farmer,
            NotificationType::ListingLowStock,
            NotificationType::ListingLowStock->label(),
            "{$listing->title} is down to {$quantity} {$unit}.",
            $listing,
        );
    }

    /**
     * A floor rose above this listing's price, either because the Super Admin
     * raised the system floor or because the farm raised its own. The listing
     * is stranded and the seller has to decide, because the system never moves
     * a farmer's price for them.
     *
     * $effectiveFloor is the farm's floor after the raise, the number the
     * seller's price is now validated against. Reading the crop type here would
     * quote the system floor to a seller whose farm sits above it, and the
     * seller would set a price that is still rejected.
     */
    /**
     * The listing's unit cannot convert into a crop that is now guarded.
     * Same notification type as a stranded price: the seller has to pick an
     * allowed unit, and buyers no longer see the listing until they do.
     */
    public function listingUnitNotAllowed(User $farmer, Listing $listing): InAppNotification
    {
        $unit = $listing->unit?->value ?? 'its current unit';
        $reason = $listing->cropType === null
            ? 'Choose a unit this crop allows.'
            : app(UnitConverter::class)->refusalMessage($listing->cropType, $listing->farm_id);

        return $this->send(
            $farmer,
            NotificationType::FloorPriceRaised,
            NotificationType::FloorPriceRaised->label(),
            "{$listing->title} is sold per {$unit}, which is no longer allowed. {$reason}",
            $listing,
        );
    }

    public function floorPriceRaised(User $farmer, Listing $listing, float $effectiveFloor): InAppNotification
    {
        $floor = number_format($effectiveFloor, 2, '.', '');

        return $this->send(
            $farmer,
            NotificationType::FloorPriceRaised,
            NotificationType::FloorPriceRaised->label(),
            "{$listing->title} is priced below the new floor of PHP {$floor}. Update the price to keep selling.",
            $listing,
        );
    }

    /**
     * A floor rose, the listing price still clears it, but the active tawad
     * rule no longer does. Checkout does not refuse the order: it silently
     * drops the discount, so the seller's advertised tawad stops applying
     * without anyone being told. This tells them. Decision 9.
     *
     * Same notification type as a stranded price, because the cause is the
     * same event: the floor went up.
     */
    public function tawadStrandedByFloor(User $farmer, Listing $listing, float $effectiveFloor): InAppNotification
    {
        $floor = number_format($effectiveFloor, 2, '.', '');

        return $this->send(
            $farmer,
            NotificationType::FloorPriceRaised,
            NotificationType::FloorPriceRaised->label(),
            "The floor for {$listing->title} rose to PHP {$floor}. Your tawad would take the price below it, so it will not apply at checkout until you lower the tawad or raise the price.",
            $listing,
        );
    }

    /**
     * The discount ceiling fell below this listing's active tawad, because the
     * Super Admin lowered the system maximum or the farm lowered its own.
     * Checkout skips a rule above the ceiling and charges the listed price, so
     * without this the seller's advertised tawad stops applying unannounced.
     * Decision 16.
     */
    public function tawadCeilingLowered(User $farmer, Listing $listing, float $effectiveCeiling): InAppNotification
    {
        $ceiling = number_format($effectiveCeiling, 2, '.', '');

        return $this->send(
            $farmer,
            NotificationType::TawadCeilingLowered,
            NotificationType::TawadCeilingLowered->label(),
            "The maximum tawad for {$listing->title} is now PHP {$ceiling}. Your tawad is above it, so it will not apply at checkout until you lower it.",
            $listing,
        );
    }

    public function listingTakenDown(User $farmer, Listing $listing): InAppNotification
    {
        $reason = filled($listing->takedown_reason)
            ? " Reason: {$listing->takedown_reason}"
            : '';

        return $this->send(
            $farmer,
            NotificationType::ListingTakenDown,
            NotificationType::ListingTakenDown->label(),
            "{$listing->title} was taken down by an administrator.{$reason}",
            $listing,
        );
    }

    public function listingRestored(User $farmer, Listing $listing): InAppNotification
    {
        return $this->send(
            $farmer,
            NotificationType::ListingRestored,
            NotificationType::ListingRestored->label(),
            "{$listing->title} was restored by an administrator.",
            $listing,
        );
    }

    public function accountApproved(User $user): InAppNotification
    {
        return $this->send(
            $user,
            NotificationType::AccountApproved,
            NotificationType::AccountApproved->label(),
            'Your account has been approved. You can now sign in and start selling.',
        );
    }

    /**
     * A currently-active farm announcement reaches that farm's farmer-sellers.
     * Buyers are never notified.
     */
    public function farmAnnouncement(User $farmer, FarmAnnouncement $announcement): InAppNotification
    {
        return $this->send(
            $farmer,
            NotificationType::FarmAnnouncement,
            $announcement->title,
            $announcement->body,
            $announcement,
        );
    }

    /**
     * Super Admin hid or removed a farm FAQ override. The farm's Content
     * Editor is told; farmer-sellers are not.
     */
    public function faqEntryModerated(User $editor, string $label, bool $deleted, ?Farm $farm = null): InAppNotification
    {
        $body = $deleted
            ? "FAQ \"{$label}\" was deleted."
            : "FAQ \"{$label}\" was deactivated.";

        return $this->send(
            $editor,
            NotificationType::FaqEntryModerated,
            NotificationType::FaqEntryModerated->label(),
            $body,
            $farm,
        );
    }

    public function faqEntryUpdatedAfterModeration(User $admin, string $label, ?Farm $farm = null): InAppNotification
    {
        return $this->send(
            $admin,
            NotificationType::FaqEntryModerated,
            NotificationType::FaqEntryModerated->label(),
            "FAQ answer updated after moderation: {$label}",
            $farm,
        );
    }

    /**
     * A new chat message reaches the other party on the order, never the
     * sender and never the Super Admin.
     */
    public function accountDeletionRequested(User $admin, User $requester, Model $request): InAppNotification
    {
        return $this->send(
            $admin,
            NotificationType::AccountDeletionRequested,
            NotificationType::AccountDeletionRequested->label(),
            "{$requester->name} requested deletion of their account.",
            $request,
        );
    }

    public function reportSubmitted(User $admin, Report $report): InAppNotification
    {
        $kind = class_basename((string) $report->reportable_type);

        return $this->send(
            $admin,
            NotificationType::ReportSubmitted,
            NotificationType::ReportSubmitted->label(),
            "A {$kind} was reported.",
            $report,
        );
    }

    public function reportResolved(User $reporter, Report $report): InAppNotification
    {
        return $this->send(
            $reporter,
            NotificationType::ReportResolved,
            NotificationType::ReportResolved->label(),
            'Your report was reviewed and resolved.',
            $report,
        );
    }

    public function reportDismissed(User $reporter, Report $report): InAppNotification
    {
        return $this->send(
            $reporter,
            NotificationType::ReportDismissed,
            NotificationType::ReportDismissed->label(),
            'Your report was reviewed and dismissed.',
            $report,
        );
    }

    public function accountDeletionRejected(User $user, Model $request): InAppNotification
    {
        $note = $request instanceof AccountDeletionRequest
            ? $request->rejection_note
            : null;
        $suffix = filled($note) ? " {$note}" : '';

        return $this->send(
            $user,
            NotificationType::AccountDeletionRejected,
            NotificationType::AccountDeletionRejected->label(),
            "Your account deletion request was rejected.{$suffix}",
            $request,
        );
    }

    public function reservationMade(User $seller, Reservation $reservation): InAppNotification
    {
        $buyerName = $reservation->buyer?->name ?? 'A buyer';
        $quantity = number_format((float) $reservation->quantity, 2, '.', '');
        $unit = $reservation->unit?->value ?? '';

        return $this->send(
            $seller,
            NotificationType::ReservationMade,
            NotificationType::ReservationMade->label(),
            "{$buyerName} reserved {$quantity} {$unit} of {$reservation->listing_name}.",
            $reservation,
        );
    }

    public function reservationConverted(User $buyer, Order $order): InAppNotification
    {
        return $this->send(
            $buyer,
            NotificationType::ReservationConverted,
            NotificationType::ReservationConverted->label(),
            'Your reservation is now an order.',
            $order,
        );
    }

    public function reservationCancelled(User $buyer, Reservation $reservation): InAppNotification
    {
        return $this->send(
            $buyer,
            NotificationType::ReservationCancelled,
            NotificationType::ReservationCancelled->label(),
            "Your reservation for {$reservation->listing_name} was cancelled.",
            $reservation,
        );
    }

    public function harvestReminder(User $farmer, Listing $listing): InAppNotification
    {
        $date = $listing->available_from?->toFormattedDateString() ?? '';

        return $this->send(
            $farmer,
            NotificationType::HarvestReminder,
            NotificationType::HarvestReminder->label(),
            "Record the actual harvest for {$listing->title} before it opens on {$date}.",
            $listing,
        );
    }

    public function expiredStockLeft(User $farmer, Listing $listing): InAppNotification
    {
        $quantity = number_format((float) $listing->quantity_available, 2, '.', '');
        $unit = $listing->unit?->value ?? '';

        return $this->send(
            $farmer,
            NotificationType::ExpiredStockLeft,
            NotificationType::ExpiredStockLeft->label(),
            "{$listing->title} ended with {$quantity} {$unit} left. Remove it as spoiled or extend the listing.",
            $listing,
        );
    }

    public function orderMessage(User $recipient, Order $order, User $sender, string $body): InAppNotification
    {
        $preview = mb_strlen($body) > 80 ? mb_substr($body, 0, 77).'...' : $body;

        return $this->send(
            $recipient,
            NotificationType::OrderMessage,
            NotificationType::OrderMessage->label(),
            "{$sender->name}: {$preview}",
            $order,
        );
    }

    /**
     * Email is a secondary channel. An unmapped type simply does not send one.
     */
    private function queueEmail(User $user, NotificationType $type, ?Model $related): void
    {
        if (! $user->hasVerifiedEmail() || $related === null) {
            return;
        }

        $mailable = match ($type) {
            NotificationType::ListingLowStock => $related instanceof Listing
                ? new ListingLowStockMail($related)
                : null,
            default => null,
        };

        if ($mailable) {
            try {
                Mail::to($user)->queue($mailable);
            } catch (Throwable $exception) {
                // In-app inbox is the source of truth; mail is best-effort.
                report($exception);
            }
        }
    }
}
