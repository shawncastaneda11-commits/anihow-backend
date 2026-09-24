<?php

namespace App\Http\Controllers\Api\Farms;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\FarmResource;
use App\Models\Farm;

class FarmController extends Controller
{
    public function __invoke(Farm $farm): FarmResource
    {
        abort_unless($farm->is_active, 404);

        $this->authorize('view', $farm);

        $farm->load([
            'photos',
            'farmerSellers' => fn ($query) => $query
                ->where('status', UserStatus::Active)
                ->orderByRaw('coalesce(shop_name, name)'),
        ])->loadCount([
            'farmerSellers' => fn ($query) => $query
                ->where('status', UserStatus::Active),
        ]);

        return new FarmResource($farm);
    }
}
