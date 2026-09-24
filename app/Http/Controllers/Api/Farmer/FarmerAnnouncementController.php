<?php

namespace App\Http\Controllers\Api\Farmer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\FarmAnnouncementResource;
use App\Models\FarmAnnouncement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FarmerAnnouncementController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', FarmAnnouncement::class);

        $farmId = $request->user()?->farm_id;

        abort_unless($farmId !== null, 403);

        $announcements = FarmAnnouncement::query()
            ->forFarm($farmId)
            ->active()
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->get();

        return FarmAnnouncementResource::collection($announcements);
    }
}
