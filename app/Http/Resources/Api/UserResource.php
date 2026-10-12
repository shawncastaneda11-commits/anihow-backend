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
            'avatar_url' => $this->avatarUrl(),
            'status' => $this->status->value,
            'email_verified_at' => $this->email_verified_at,
            'must_change_password' => (bool) $this->must_change_password,
            'needs_terms' => $this->needsTermsAcceptance(),
            'terms_accepted_at' => $this->terms_accepted_at,
            'id_verification' => $this->idVerificationSummary(),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')->values()),
            'farm' => $this->whenLoaded(
                'farm',
                fn (): ?array => $this->farm === null
                    ? null
                    : [
                        'id' => $this->farm->id,
                        'name' => $this->farm->name,
                        'is_organic_certified' => $this->farm->isOrganicCertified(),
                        'features' => [
                            'value_added' => $this->farm->allowsValueAdded(),
                            'reservations' => $this->farm->allowsReservations(),
                            'tawad' => $this->farm->allowsTawad(),
                            'walk_in' => $this->farm->allowsWalkIn(),
                        ],
                    ],
            ),
            'permissions' => $this->getAllPermissions()->pluck('name')->values(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * @return array{required: bool, verified: bool, status: string, id_type: ?string, rejection_reason: ?string, submitted_at: ?string, reviewed_at: ?string}|null
     */
    private function idVerificationSummary(): ?array
    {
        if (! $this->isBuyer()) {
            return null;
        }

        $latest = $this->latestIdVerification;
        $verified = $this->id_verified_at !== null;

        return [
            'required' => $this->mustVerifyIdToOrder(),
            'verified' => $verified,
            'status' => $verified ? 'approved' : ($latest?->status->value ?? 'none'),
            'id_type' => $latest?->id_type->value,
            'rejection_reason' => $latest?->rejection_reason,
            'submitted_at' => $latest?->created_at?->toIso8601String(),
            'reviewed_at' => ($verified ? $this->id_verified_at : $latest?->reviewed_at)?->toIso8601String(),
        ];
    }
}
