<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\SellerPaymentQr;
use App\Models\User;

class SellerPaymentQrPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageOwnOrders->value);
    }

    public function view(User $user, SellerPaymentQr $sellerPaymentQr): bool
    {
        return $sellerPaymentQr->farmer_seller_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageOwnOrders->value);
    }

    public function update(User $user, SellerPaymentQr $sellerPaymentQr): bool
    {
        return false;
    }

    public function delete(User $user, SellerPaymentQr $sellerPaymentQr): bool
    {
        return $sellerPaymentQr->farmer_seller_id === $user->id;
    }
}
