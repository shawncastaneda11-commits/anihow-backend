<?php

namespace App\Actions\Accounts;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Farm;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * COSEL creating a farm's farmer-seller accounts in one go from rows pasted
 * from a spreadsheet: name, email, and an optional mobile number per line,
 * separated by tabs or commas.
 *
 * No password is shown or sent. Each account starts with an unusable random
 * password; COSEL hands each person a temporary password in person with
 * "Reset temporary password", exactly as for a single account.
 */
class BulkCreateFarmerSellers
{
    public const MAX_ROWS = 200;

    /**
     * @return array{created: list<string>, skipped: list<string>}
     */
    public function handle(Farm $farm, string $rows): array
    {
        $created = [];
        $skipped = [];
        $lines = array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', $rows) ?: []),
            fn (string $line): bool => $line !== '',
        ));

        foreach (array_slice($lines, 0, self::MAX_ROWS) as $number => $line) {
            $cells = array_map('trim', preg_split('/\t|,/', $line) ?: []);
            [$name, $email, $phone] = array_pad($cells, 3, null);
            $label = 'Line '.($number + 1);

            if (strcasecmp((string) $name, 'name') === 0) {
                continue; // a header row copied with the data
            }

            $check = Validator::make(
                ['name' => $name, 'email' => $email, 'phone' => $phone],
                [
                    'name' => ['required', 'string', 'max:255'],
                    'email' => ['required', 'email', 'max:255'],
                    'phone' => ['nullable', 'string', 'max:30'],
                ],
            );

            if ($check->fails()) {
                $skipped[] = "{$label}: ".$check->errors()->first();

                continue;
            }

            if (User::withTrashed()->where('email', $email)->exists()) {
                $skipped[] = "{$label}: {$email} already has an account.";

                continue;
            }

            DB::transaction(function () use ($farm, $name, $email, $phone): void {
                $user = User::query()->create([
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone ?: null,
                    'password' => Str::password(32),
                    'status' => UserStatus::Active,
                    'farm_id' => $farm->getKey(),
                ]);
                $user->forceFill([
                    'email_verified_at' => now(),
                    'must_change_password' => true,
                ])->save();
                $user->syncRoles(Role::FarmerSeller);
            });

            $created[] = $email;
        }

        if (count($lines) > self::MAX_ROWS) {
            $skipped[] = 'Only the first '.self::MAX_ROWS.' lines were read. Add the rest in another batch.';
        }

        return ['created' => $created, 'skipped' => $skipped];
    }
}
