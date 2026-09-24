<?php

namespace App\Actions\Privacy;

use App\Enums\AccountDeletionStatus;
use App\Models\AccountDeletionRequest;
use App\Models\User;
use App\Support\InAppNotifier;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RejectAccountDeletionRequestAction
{
    public function __construct(private InAppNotifier $notifier) {}

    public function handle(User $admin, AccountDeletionRequest $request, string $note): void
    {
        Gate::forUser($admin)->authorize('process', $request);

        $note = trim($note);

        if ($note === '') {
            throw ValidationException::withMessages([
                'rejection_note' => 'A rejection note is required.',
            ]);
        }

        $request->update([
            'status' => AccountDeletionStatus::Rejected,
            'rejection_note' => $note,
            'processed_by' => $admin->id,
            'processed_at' => now(),
        ]);

        $subject = $request->user;

        if ($subject !== null) {
            $this->notifier->accountDeletionRejected($subject, $request);
        }
    }
}
