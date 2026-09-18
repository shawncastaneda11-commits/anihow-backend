<?php

namespace App\Enums;

enum ReservationActor: string
{
    case Buyer = 'buyer';
    case FarmerSeller = 'farmer_seller';
}
