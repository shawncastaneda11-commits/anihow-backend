<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;

class ReservationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isBuyer() || $user->isFarmerSeller() || $user->isSuperAdmin();
    }

    public function view(User $user, Reservation $reservation): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isBuyer()) {
            return $reservation->isOwnedByBuyer($user);
        }

        return $user->isFarmerSeller() && $reservation->isOwnedByFarmer($user);
    }

    public function create(User $user): bool
    {
        return $user->isBuyer();
    }

    public function cancelAsBuyer(User $user, Reservation $reservation): bool
    {
        return $user->isBuyer() && $reservation->isOwnedByBuyer($user);
    }

    public function manageAsFarmer(User $user, Reservation $reservation): bool
    {
        return $user->isFarmerSeller() && $reservation->isOwnedByFarmer($user);
    }
}
