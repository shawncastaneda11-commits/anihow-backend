<?php

namespace App\Support\Accounts;

use App\Enums\Role;
use App\Models\User;

/**
 * The role shown beside a chat message. A seller who also buys is the
 * buyer in a chat where they are the buyer, and the seller everywhere else.
 */
final class MessageRole
{
    /**
     * @param  \Closure(): (int|string|null)  $buyerId  read only when needed
     */
    public static function for(?User $author, \Closure $buyerId): ?string
    {
        if ($author === null) {
            return null;
        }

        if (! $author->alsoBuys()) {
            return $author->roles->first()?->name;
        }

        return (int) $buyerId() === (int) $author->id ? Role::Buyer->value : Role::FarmerSeller->value;
    }
}
