<?php

namespace App\Actions\Privacy;

use App\Enums\AccountDeletionStatus;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CancelAccountDeletionRequestAction
{
    public function handle(User $user): void
    {
        $pending = $user->accountDeletionRequests()
            ->where('status', AccountDeletionStatus::Pending)
            ->latest()
            ->first();

        if ($pending === null) {
            throw ValidationException::withMessages([
                'status' => 'There is no pending account deletion request to cancel.',
            ]);
        }

        $pending->delete();
    }
}
