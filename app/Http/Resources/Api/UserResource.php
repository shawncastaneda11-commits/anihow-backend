<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'shop_name' => $this->shop_name,
            'bio' => $this->bio,
            'contact' => $this->contact,
            'location' => $this->location,
            'status' => $this->status->value,
            'email_verified_at' => $this->email_verified_at,
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')->values()),
            'farm' => $this->whenLoaded(
                'farm',
                fn (): ?array => $this->farm === null
                    ? null
                    : [
                        'id' => $this->farm->id,
                        'name' => $this->farm->name,
                    ],
            ),
            'permissions' => $this->getAllPermissions()->pluck('name')->values(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
