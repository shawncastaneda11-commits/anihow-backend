<?php

namespace App\Actions\IdVerification;

use App\Enums\IdVerificationStatus;
use App\Enums\Permission;
use App\Models\IdVerification;
use App\Models\User;
use App\Support\ImageVariants;
use App\Support\InAppNotifier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Approves or rejects a pending ID check. Either way the photos are deleted
 * right away; only the outcome is kept.
 */
class ReviewIdVerificationAction
{
    public function __construct(
        private ImageVariants $images,
        private InAppNotifier $notifier,
    ) {}

    public function approve(User $admin, IdVerification $verification): IdVerification
    {
        return $this->review($admin, $verification, IdVerificationStatus::Approved, null);
    }

    public function reject(User $admin, IdVerification $verification, string $reason): IdVerification
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['rejection_reason' => 'A reason is required.']);
        }

        return $this->review($admin, $verification, IdVerificationStatus::Rejected, $reason);
    }

    private function review(User $admin, IdVerification $verification, IdVerificationStatus $outcome, ?string $reason): IdVerification
    {
        if (! $admin->can(Permission::ManageAccounts->value)) {
            throw new AuthorizationException;
        }

        $paths = DB::transaction(function () use ($admin, $verification, $outcome, $reason): array {
            $locked = IdVerification::query()->whereKey($verification->id)->lockForUpdate()->first();

            if ($locked === null || ! $locked->isPending()) {
                throw ValidationException::withMessages(['status' => 'This ID check has already been reviewed.']);
            }

            $paths = array_filter([$locked->front_path, $locked->selfie_path]);

            $locked->update([
                'status' => $outcome,
                'rejection_reason' => $reason,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'front_path' => null,
                'selfie_path' => null,
            ]);

            if ($outcome === IdVerificationStatus::Approved) {
                $locked->user()->firstOrFail()->forceFill(['id_verified_at' => now()])->save();
            }

            return $paths;
        });

        $disk = Storage::disk('local');
        foreach ($paths as $path) {
            $this->images->delete($path, $disk);
        }

        $verification->refresh();
        $user = $verification->user;

        if ($user !== null) {
            $outcome === IdVerificationStatus::Approved
                ? $this->notifier->idVerificationApproved($user, $verification)
                : $this->notifier->idVerificationRejected($user, $verification);
        }

        return $verification;
    }
}
