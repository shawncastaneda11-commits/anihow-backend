<?php

namespace App\Actions\Privacy;

use App\Enums\AccountDeletionStatus;
use App\Enums\Permission;
use App\Models\AccountDeletionRequest;
use App\Models\User;
use App\Support\InAppNotifier;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class RequestAccountDeletionAction
{
    public function __construct(private InAppNotifier $notifier) {}

    public function handle(User $user, ?string $reason = null): AccountDeletionRequest
    {
        if ($user->hasOpenMarketplaceOrders()) {
            throw ValidationException::withMessages([
                'status' => "You can't request deletion while you have orders in progress (placed, confirmed, or ready). Finished or cancelled orders don't block it.",
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

        // Keep the request even if admin fans-out fails — otherwise the app
        // looks broken and Filament never receives a row to process.
        try {
            User::query()
                ->permission(Permission::ManageAccounts->value)
                ->whereKeyNot($user->id)
                ->each(fn (User $admin): mixed => $this->notifier->accountDeletionRequested($admin, $user, $request));
        } catch (Throwable $exception) {
            Log::warning('Failed to notify admins of account deletion request.', [
                'deletion_request_id' => $request->id,
                'user_id' => $user->id,
                'message' => $exception->getMessage(),
            ]);
        }

        return $request;
    }
}
