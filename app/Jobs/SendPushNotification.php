<?php

namespace App\Jobs;

use App\Enums\NotificationType;
use App\Models\InAppNotification;
use App\Support\FcmPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [15, 60];

    public function __construct(public int $notificationId)
    {
        $this->afterCommit();
    }

    public function handle(FcmPush $push): void
    {
        $notification = InAppNotification::query()->find($this->notificationId);
        $user = $notification?->user;
        $type = $notification?->type;

        if ($notification === null || $user === null || ! $type instanceof NotificationType) {
            return;
        }

        $category = $type->pushCategory();
        if (! $user->allowsPushCategory($category)) {
            return;
        }

        if (! $push->configured()) {
            $push->noteMissingCredentials();

            return;
        }

        if (! $user->deviceTokens()->exists()) {
            return;
        }

        [$title, $body] = self::privacyText($category);
        $push->send($user, $category ?? 'general', $title, $body, [
            'notification_id' => (string) $notification->id,
            'type' => $type->value,
            'related_type' => (string) ($notification->related_type ?? ''),
            'related_id' => $notification->related_id === null ? '' : (string) $notification->related_id,
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function privacyText(?string $category): array
    {
        $title = match ($category) {
            'orders' => 'Order update',
            'payments' => 'Payment update',
            'chats' => 'New message',
            'farm_updates' => 'Farm update',
            default => 'AniHow',
        };

        return [$title, 'Open AniHow to see it.'];
    }
}
