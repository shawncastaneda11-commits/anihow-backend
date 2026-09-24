<?php

namespace App\Actions\Announcements;

use App\Enums\UserStatus;
use App\Models\FarmAnnouncement;
use App\Models\User;
use App\Support\InAppNotifier;

class NotifyFarmAnnouncementRecipientsAction
{
    public function __construct(private InAppNotifier $notifier) {}

    public function handle(FarmAnnouncement $announcement): void
    {
        $announcement->refresh();

        if ($announcement->notified_at !== null || ! $announcement->isCurrentlyActive()) {
            return;
        }

        $announcement->loadMissing('farm');

        $announcement->farm?->farmerSellers()
            ->where('status', UserStatus::Active)
            ->get()
            ->each(fn (User $seller) => $this->notifier->farmAnnouncement($seller, $announcement));

        $announcement->update(['notified_at' => now()]);
    }
}
