<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Enums\Permission;
use App\Models\Order;
use App\Models\User;

/**
 * A Content Editor holds view_farm_analytics but not view_all_orders. They see
 * their farm's aggregate figures, never the order ledger. That distinction is
 * deliberate and the panel will probe it.
 */
class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewAllOrders->value)
            || $user->can(Permission::ManageOwnOrders->value)
            || $user->can(Permission::PlaceOrders->value);
    }

    public function view(User $user, Order $order): bool
    {
        if ($user->can(Permission::ViewAllOrders->value)) {
            return true;
        }

        return $order->isOwnedByBuyer($user) || $order->isOwnedByFarmer($user);
    }

    /**
     * App orders only. Walk-ins have no buyer account to chat with.
     */
    public function chat(User $user, Order $order): bool
    {
        return $this->view($user, $order) && ! $order->isWalkIn();
    }

    /**
     * Only the buyer or seller on that order may send. Super Admin may read
     * in Filament but never posts into the thread. Cancelled threads are
     * read-only.
     */
    public function sendMessage(User $user, Order $order): bool
    {
        if (! $this->chat($user, $order)) {
            return false;
        }

        if ($order->status === OrderStatus::Cancelled) {
            return false;
        }

        return $order->isOwnedByBuyer($user) || $order->isOwnedByFarmer($user);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::PlaceOrders->value);
    }

    /**
     * The farmer-seller advances the status. Transitions still go through
     * OrderStateMachine, which re-checks the actor; this gates the route.
     */
    public function advance(User $user, Order $order): bool
    {
        return $user->can(Permission::ManageOwnOrders->value)
            && $order->isOwnedByFarmer($user);
    }

    /**
     * A buyer may cancel only before the seller confirms. A seller may cancel
     * at any non-terminal point, which is how a no-show is recorded.
     */
    public function cancel(User $user, Order $order): bool
    {
        if ($order->isOwnedByFarmer($user)) {
            return $user->can(Permission::ManageOwnOrders->value);
        }

        return $order->isOwnedByBuyer($user) && $order->canBeCancelledByBuyer();
    }
}
