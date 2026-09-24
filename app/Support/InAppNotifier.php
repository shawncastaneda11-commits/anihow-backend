<?php

namespace App\Support;

use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use App\Mail\ListingLowStockMail;
use App\Models\Farm;
use App\Models\FarmAnnouncement;
use App\Models\InAppNotification;
use App\Models\Listing;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;

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
            "{$buyerName} placed order {$order->order_number} totaling PHP {$total}.",
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
            "Order {$order->order_number} is now {$status->label()}.",
            $order,
        );
    }

    public function listingLowStock(User $farmer, Listing $listing): InAppNotification
    {
        $unit = $listing->cropType?->unit_of_measure?->value ?? '';
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
     * A new chat message reaches the other party on the order, never the
     * sender and never the Super Admin.
     */
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

    public function orderMessage(User $recipient, Order $order, User $sender, string $body): InAppNotification
    {
        $preview = mb_strlen($body) > 80 ? mb_substr($body, 0, 77).'...' : $body;

        return $this->send(
            $recipient,
            NotificationType::OrderMessage,
            NotificationType::OrderMessage->label(),
            "{$sender->name} on order {$order->order_number}: {$preview}",
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
            Mail::to($user)->queue($mailable);
        }
    }
}
