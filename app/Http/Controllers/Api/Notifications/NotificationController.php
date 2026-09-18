<?php

namespace App\Http\Controllers\Api\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\InAppNotificationResource;
use App\Models\InAppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', InAppNotification::class);

        $notifications = $request->user()
            ->inAppNotifications()
            ->latest()
            ->orderByDesc('id')
            ->paginate();

        return InAppNotificationResource::collection($notifications);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $this->authorize('viewAny', InAppNotification::class);

        return response()->json([
            'data' => [
                'unread_count' => $request->user()
                    ->inAppNotifications()
                    ->whereNull('read_at')
                    ->count(),
            ],
        ]);
    }

    public function read(Request $request, InAppNotification $inAppNotification): InAppNotificationResource
    {
        $this->authorize('update', $inAppNotification);

        $inAppNotification->markRead();

        return (new InAppNotificationResource($inAppNotification->refresh()))
            ->additional(['message' => 'Notification marked as read.']);
    }

    public function readAll(Request $request): JsonResponse
    {
        $this->authorize('viewAny', InAppNotification::class);

        $request->user()
            ->inAppNotifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'message' => 'All notifications marked as read.',
        ]);
    }
}
