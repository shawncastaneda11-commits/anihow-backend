<?php

namespace App\Http\Resources\Api;

use App\Models\Farm;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class SellerHelpResource extends JsonResource
{
    /**
     * @return array{
     *     contact: array{office: ?string, email: ?string, phone: ?string},
     *     temporary_password_days: int,
     *     farms: list<array{id: int, name: string, municipality: ?string, contact_person: ?string, contact_number: ?string}>
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var Collection<int, Farm> $farms */
        $farms = $this->resource;

        return [
            'contact' => [
                'office' => $this->blank(config('anihow.seller_help.office')),
                'email' => $this->blank(config('anihow.seller_help.email')),
                'phone' => $this->blank(config('anihow.seller_help.phone')),
            ],
            'temporary_password_days' => (int) config('anihow.auth.temporary_password_days'),
            'farms' => $farms
                ->map(fn (Farm $farm): array => [
                    'id' => $farm->id,
                    'name' => $farm->name,
                    'municipality' => $this->blank($farm->municipality),
                    'contact_person' => $this->blank($farm->contact_person),
                    'contact_number' => $this->blank($farm->contact_number),
                ])
                ->values()
                ->all(),
        ];
    }

    private function blank(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
