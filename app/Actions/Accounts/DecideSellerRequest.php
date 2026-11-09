<?php

namespace App\Actions\Accounts;

use App\Enums\Role;
use App\Enums\SellerRequestStatus;
use App\Enums\UserStatus;
use App\Models\SellerRequest;
use App\Models\User;
use App\Support\Accounts\AccountAuthority;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * COSEL deciding a Content Editor's request for a new farmer-seller.
 *
 * Adding creates the account on the requested farm and returns a temporary
 * password, shown once to COSEL to hand over in person. The Content Editor
 * sees the outcome, never the password.
 */
class DecideSellerRequest
{
    /**
     * @return array{user: User, password: string}
     */
    public function add(User $cosel, SellerRequest $request): array
    {
        $this->authorize($cosel, $request);

        if (User::withTrashed()->where('email', $request->email)->exists()) {
            throw ValidationException::withMessages([
                'email' => "{$request->email} already has an AniHow account. Don't add this request, and ask for another email.",
            ]);
        }

        return DB::transaction(function () use ($cosel, $request): array {
            $user = User::query()->create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Str::password(32),
                'status' => UserStatus::Active,
                'farm_id' => $request->farm_id,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->syncRoles(Role::FarmerSeller);
            $password = $user->issueTemporaryPassword();

            $request->update([
                'status' => SellerRequestStatus::Added,
                'decided_by' => $cosel->getKey(),
                'decided_at' => now(),
                'user_id' => $user->getKey(),
            ]);

            return ['user' => $user, 'password' => $password];
        });
    }

    public function decline(User $cosel, SellerRequest $request, string $reason): void
    {
        $this->authorize($cosel, $request);

        $request->update([
            'status' => SellerRequestStatus::NotAdded,
            'decision_reason' => mb_substr(trim($reason), 0, 500),
            'decided_by' => $cosel->getKey(),
            'decided_at' => now(),
        ]);
    }

    private function authorize(User $cosel, SellerRequest $request): void
    {
        abort_unless(AccountAuthority::managesPeople($cosel), 403);

        if (! $request->isWaiting()) {
            throw ValidationException::withMessages(['status' => 'This request was already decided.']);
        }
    }
}
