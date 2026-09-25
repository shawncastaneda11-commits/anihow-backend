<?php

namespace App\Console\Commands;

use App\Actions\Announcements\NotifyFarmAnnouncementRecipientsAction;
use App\Models\FarmAnnouncement;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('announcements:notify-due')]
#[Description('Notify farmer-sellers of farm announcements that have just gone live')]
class NotifyDueFarmAnnouncements extends Command
{
    public function handle(NotifyFarmAnnouncementRecipientsAction $action): int
    {
        FarmAnnouncement::query()
            ->active()
            ->whereNull('notified_at')
            ->orderBy('id')
            ->each(fn (FarmAnnouncement $announcement) => $action->handle($announcement));

        return self::SUCCESS;
    }
}
