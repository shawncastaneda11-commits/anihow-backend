<?php

namespace App\Actions\Privacy;

use App\Enums\AccountDeletionStatus;
use App\Models\AccountDeletionRequest;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ApproveAccountDeletionRequestAction
{
    public function __construct(private AnonymizeUserAction $anonymize) {}

    public function handle(User $admin, AccountDeletionRequest $request): void
    {
        Gate::forUser($admin)->authorize('process', $request);

        $subject = $request->user;

        if ($subject === null) {
            throw ValidationException::withMessages([
                'status' => 'The account for this request no longer exists.',
            ]);
        }

        $this->anonymize->handle($subject);

        $request->update([
            'status' => AccountDeletionStatus::Completed,
            'processed_by' => $admin->id,
            'processed_at' => now(),
        ]);
    }
}
