<?php

namespace App\Support;

use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use App\Mail\ListingLowStockMail;
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
     * The Super Admin raised a crop type's floor above this listing's price.
     * The listing is stranded and the seller has to decide, because the system
     * never moves a farmer's price for them.
     */
    public function floorPriceRaised(User $farmer, Listing $listing): InAppNotification
    {
        $floor = number_format((float) $listing->cropType->floor_price, 2, '.', '');

        return $this->send(
            $farmer,
            NotificationType::FloorPriceRaised,
            NotificationType::FloorPriceRaised->label(),
            "{$listing->title} is priced below the new floor of PHP {$floor}. Update the price to keep selling.",
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
