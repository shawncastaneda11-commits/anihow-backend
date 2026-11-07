<?php

namespace App\Actions\Announcements;

use App\Enums\NotificationType;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\InAppNotifier;

/**
 * COSEL's message to every active app user, or to all farmer-sellers or all
 * buyers. It arrives as an in-app notification (and a push where the user has
 * one), like a farm announcement but for the whole system. The send itself is
 * recorded in the activity log.
 */
class SendSystemAnnouncementAction
{
    public const AUDIENCES = [
        'everyone' => 'Everyone (farmer-sellers and buyers)',
        'farmer_sellers' => 'All farmer-sellers',
        'buyers' => 'All buyers',
    ];

    public function __construct(private readonly InAppNotifier $notifier) {}

    /**
     * @return int how many people it was sent to
     */
    public function handle(User $sender, string $audience, string $title, string $body): int
    {
        $roles = match ($audience) {
            'farmer_sellers' => [Role::FarmerSeller->value],
            'buyers' => [Role::Buyer->value],
            default => [Role::FarmerSeller->value, Role::Buyer->value],
        };

        $sent = 0;

        User::query()
            ->where('status', UserStatus::Active)
            ->role($roles)
            ->chunkById(200, function ($users) use ($title, $body, &$sent): void {
                foreach ($users as $user) {
                    $this->notifier->send($user, NotificationType::SystemAnnouncement, $title, $body);
                    $sent++;
                }
            });

        ActivityLog::query()->create([
            'user_id' => $sender->getKey(),
            'action' => 'announced',
            'subject_type' => 'announcement',
            'subject_label' => $title,
            'changes' => ['audience' => self::AUDIENCES[$audience] ?? $audience, 'recipients' => $sent, 'body' => $body],
        ]);

        return $sent;
    }
}
