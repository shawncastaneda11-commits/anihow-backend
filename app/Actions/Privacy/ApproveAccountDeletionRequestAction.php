<?php

namespace App\Actions\Privacy;

use App\Enums\AccountDeletionStatus;
use App\Mail\AccountDeletionCompletedMail;
use App\Models\AccountDeletionRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
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

        $originalEmail = $subject->email;
        $originalName = $subject->name;

        DB::transaction(function () use ($admin, $request): void {
            $locked = AccountDeletionRequest::query()
                ->whereKey($request->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null || ! $locked->isPending()) {
                throw ValidationException::withMessages([
                    'status' => 'This request has already been processed.',
                ]);
            }

            $user = User::query()
                ->whereKey($locked->user_id)
                ->lockForUpdate()
                ->first();

            if ($user === null) {
                throw ValidationException::withMessages([
                    'status' => 'The account for this request no longer exists.',
                ]);
            }

            if ($user->hasOpenMarketplaceOrders()) {
                throw ValidationException::withMessages([
                    'status' => 'This account still has open orders and cannot be anonymised.',
                ]);
            }

            $this->anonymize->handle($user);

            $locked->update([
                'status' => AccountDeletionStatus::Completed,
                'processed_by' => $admin->id,
                'processed_at' => now(),
            ]);
        });

        Mail::to($originalEmail)->queue(new AccountDeletionCompletedMail($originalName));
    }
}
