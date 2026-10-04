<?php

namespace App\Http\Controllers\Api\Buyer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Announcements\BuyerAnnouncementIndexRequest;
use App\Http\Resources\Api\BuyerFarmAnnouncementResource;
use App\Models\FarmAnnouncement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BuyerAnnouncementController extends Controller
{
    public function index(BuyerAnnouncementIndexRequest $request): AnonymousResourceCollection
    {
        $announcements = FarmAnnouncement::query()
            ->active()
            ->publicAudience()
            ->whereHas('farm', fn (Builder $farm): Builder => $farm->active())
            ->with('farm:id,name,cover_photo_path')
            ->when(
                $request->validated('farm_id'),
                fn (Builder $query, int|string $farmId): Builder => $query->forFarm((int) $farmId),
            )
            ->when(
                $request->boolean('following'),
                fn (Builder $query): Builder => $query->whereIn(
                    'farm_announcements.farm_id',
                    $request->user()->farmFavorites()->select('farm_id'),
                ),
            )
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->paginate();

        return BuyerFarmAnnouncementResource::collection($announcements);
    }
}
