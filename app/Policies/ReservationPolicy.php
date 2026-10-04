<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;

class ReservationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PlaceOrders->value)
            || $user->can(Permission::ManageOwnListings->value);
    }

    public function view(User $user, Reservation $reservation): bool
    {
        if ((int) $reservation->buyer_id === (int) $user->id) {
            return true;
        }

        return (int) $reservation->farmer_seller_id === (int) $user->id
            && $user->can(Permission::ManageOwnListings->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::PlaceOrders->value);
    }

    public function cancel(User $user, Reservation $reservation): bool
    {
        return (int) $reservation->buyer_id === (int) $user->id
            && $reservation->status === ReservationStatus::Active
            && $user->can(Permission::PlaceOrders->value);
    }
}
