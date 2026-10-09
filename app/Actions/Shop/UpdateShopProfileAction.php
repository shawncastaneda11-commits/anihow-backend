<?php

namespace App\Actions\Shop;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class UpdateShopProfileAction
{
    /**
     * @param  array{shop_name?: string|null, bio?: string|null, location?: string|null, contact?: string|null, accepts_online_payment?: bool, payment_time_limit_hours?: int}  $data
     */
    public function handle(User $farmer, array $data): User
    {
        if (($data['accepts_online_payment'] ?? false) === true && ! $farmer->paymentQrs()->exists()) {
            throw ValidationException::withMessages([
                'accepts_online_payment' => 'Add a QR code first.',
            ]);
        }

        $farmer->update([
            'shop_name' => $data['shop_name'] ?? $farmer->shop_name,
            'bio' => array_key_exists('bio', $data) ? $data['bio'] : $farmer->bio,
            'location' => array_key_exists('location', $data) ? $data['location'] : $farmer->location,
            'contact' => array_key_exists('contact', $data) ? $data['contact'] : $farmer->contact,
            'accepts_online_payment' => array_key_exists('accepts_online_payment', $data)
                ? (bool) $data['accepts_online_payment']
                : $farmer->accepts_online_payment,
            'payment_time_limit_hours' => array_key_exists('payment_time_limit_hours', $data)
                ? (int) $data['payment_time_limit_hours']
                : $farmer->payment_time_limit_hours,
        ]);

        return $farmer->refresh();
    }
}
