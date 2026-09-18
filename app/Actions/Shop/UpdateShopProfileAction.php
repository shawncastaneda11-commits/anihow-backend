<?php

namespace App\Actions\Shop;

use App\Models\User;

class UpdateShopProfileAction
{
    /**
     * @param  array{shop_name?: string|null, bio?: string|null, location?: string|null, contact?: string|null}  $data
     */
    public function handle(User $farmer, array $data): User
    {
        $farmer->update([
            'shop_name' => $data['shop_name'] ?? $farmer->shop_name,
            'bio' => array_key_exists('bio', $data) ? $data['bio'] : $farmer->bio,
            'location' => array_key_exists('location', $data) ? $data['location'] : $farmer->location,
            'contact' => array_key_exists('contact', $data) ? $data['contact'] : $farmer->contact,
        ]);

        return $farmer->refresh();
    }
}
