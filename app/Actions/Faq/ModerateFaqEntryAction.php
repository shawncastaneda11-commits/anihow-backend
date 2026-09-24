<?php

namespace App\Actions\Faq;

use App\Enums\Permission;
use App\Models\FaqEntry;
use App\Models\User;
use App\Support\InAppNotifier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

class ModerateFaqEntryAction
{
    public function __construct(private InAppNotifier $notifier) {}

    public function deactivate(User $actor, FaqEntry $entry): void
    {
        Gate::forUser($actor)->authorize('deactivate', $entry);

        $entry->update(['is_active' => false]);
        $this->notifyEditor($entry, deleted: false);
    }

    public function reactivate(User $actor, FaqEntry $entry): void
    {
        Gate::forUser($actor)->authorize('deactivate', $entry);

        $entry->update(['is_active' => true]);
    }

    public function deleteFarmRow(User $actor, FaqEntry $entry): void
    {
        Gate::forUser($actor)->authorize('delete', $entry);

        if ($entry->isSystemWide() || ! $actor->can(Permission::ManageSystemFaq->value)) {
            throw new AuthorizationException;
        }

        $this->notifyEditor($entry, deleted: true);
        $entry->delete();
    }

    private function notifyEditor(FaqEntry $entry, bool $deleted): void
    {
        $entry->loadMissing('farm.contentEditor');
        $editor = $entry->farm?->contentEditor;

        if ($editor === null) {
            return;
        }

        $this->notifier->faqEntryModerated($editor, $entry->label, $deleted, $entry->farm);
    }
}
