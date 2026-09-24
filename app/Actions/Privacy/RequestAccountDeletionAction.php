<?php

namespace App\Actions\Privacy;

use App\Enums\AccountDeletionStatus;
use App\Enums\Permission;
use App\Models\AccountDeletionRequest;
use App\Models\User;
use App\Support\InAppNotifier;
use Illuminate\Validation\ValidationException;

class RequestAccountDeletionAction
{
    public function __construct(private InAppNotifier $notifier) {}

    public function handle(User $user, ?string $reason = null): AccountDeletionRequest
    {
        if ($user->hasOpenMarketplaceOrders()) {
            throw ValidationException::withMessages([
                'status' => 'You have open orders (placed, confirmed, or ready) that must be completed or cancelled before you can request account deletion.',
            ]);
        }

        $alreadyPending = $user->accountDeletionRequests()
            ->where('status', AccountDeletionStatus::Pending)
            ->exists();

        if ($alreadyPending) {
            throw ValidationException::withMessages([
                'status' => 'You already have a pending account deletion request.',
            ]);
        }

        $request = $user->accountDeletionRequests()->create([
            'reason' => $reason,
            'status' => AccountDeletionStatus::Pending,
        ]);

        User::query()
            ->permission(Permission::ManageAccounts->value)
            ->whereKeyNot($user->id)
            ->each(fn (User $admin): mixed => $this->notifier->accountDeletionRequested($admin, $user, $request));

        return $request;
    }
}
