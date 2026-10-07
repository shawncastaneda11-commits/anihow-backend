<?php

namespace App\Actions\Privacy;

use App\Models\User;

class UpdateOwnProfileAction
{
    /**
     * Name, phone and location only. Email stays the OTP-verified login.
     *
     * @param  array{name?: string, phone?: string|null, location?: string|null}  $data
     */
    public function handle(User $user, array $data): User
    {
        $user->update([
            'name' => $data['name'] ?? $user->name,
            'phone' => array_key_exists('phone', $data) ? $data['phone'] : $user->phone,
            'location' => array_key_exists('location', $data) ? $data['location'] : $user->location,
        ]);

        return $user->refresh();
    }
}
