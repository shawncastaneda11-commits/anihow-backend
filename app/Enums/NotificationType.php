<?php

namespace App\Enums;

enum NotificationType: string
{
    case ReservationCreated = 'reservation_created';
    case ReservationStatusChanged = 'reservation_status_changed';
    case ListingLowStock = 'listing_low_stock';
}
