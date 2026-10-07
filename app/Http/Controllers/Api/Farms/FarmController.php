<?php

namespace App\Http\Controllers\Api\Farms;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\FarmResource;
use App\Models\Farm;
use Illuminate\Http\Request;

class FarmController extends Controller
{
    public function __invoke(Request $request, Farm $farm): FarmResource
    {
        abort_unless($farm->is_active, 404);

        $this->authorize('view', $farm);

        $farm->load([
            'photos',
            'announcements' => fn ($query) => $query
                ->publicAudience()
                ->active()
                ->orderByDesc('is_pinned')
                ->orderByDesc('created_at'),
            'farmerSellers' => fn ($query) => $query
                ->where('status', UserStatus::Active)
                ->orderByRaw('coalesce(shop_name, name)'),
        ])->loadCount([
            'favorites',
            'farmerSellers' => fn ($query) => $query
                ->where('status', UserStatus::Active),
        ]);

        $buyer = $request->user();
        if ($buyer !== null) {
            $farm->loadExists([
                'favorites as is_favorited' => fn ($favorites) => $favorites
                    ->where('buyer_id', $buyer->id),
            ]);
        }

        return new FarmResource($farm);
    }
}
