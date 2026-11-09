<?php

namespace App\Actions\Accounts;

use App\Enums\Permission;
use App\Enums\SellerRequestStatus;
use App\Models\SellerRequest;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

/**
 * A Content Editor asking COSEL to add farmer-sellers to their own farm:
 * one person, or one per line (name, email, optional mobile, separated by
 * commas or tabs, as copied from a spreadsheet). Nothing is created here;
 * COSEL decides each request (DecideSellerRequest).
 */
class RequestFarmerSellers
{
    public const MAX_ROWS = 100;

    /**
     * @return array{sent: list<string>, skipped: list<string>}
     */
    public function handle(User $editor, string $rows, ?string $note = null): array
    {
        abort_unless($editor->can(Permission::RequestFarmerSellers->value) && $editor->farm_id !== null, 403);

        $sent = [];
        $skipped = [];
        $note = trim((string) $note) === '' ? null : mb_substr(trim((string) $note), 0, 500);
        $lines = array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', $rows) ?: []),
            fn (string $line): bool => $line !== '',
        ));

        foreach (array_slice($lines, 0, self::MAX_ROWS) as $number => $line) {
            [$name, $email, $phone] = array_pad(array_map('trim', preg_split('/\t|,/', $line) ?: []), 3, null);
            $label = $name !== null && $name !== '' ? $name : 'Line '.($number + 1);

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
                ['email.required' => 'needs an email'],
            );

            if ($check->fails()) {
                $skipped[] = "{$label}: ".$check->errors()->first();

                continue;
            }

            if (User::withTrashed()->where('email', $email)->exists()) {
                $skipped[] = "{$label}: {$email} already has an AniHow account.";

                continue;
            }

            if (SellerRequest::query()->where('email', $email)->where('status', SellerRequestStatus::Waiting)->exists()) {
                $skipped[] = "{$label}: already waiting for COSEL.";

                continue;
            }

            SellerRequest::query()->create([
                'farm_id' => $editor->farm_id,
                'requested_by' => $editor->getKey(),
                'name' => $name,
                'email' => $email,
                'phone' => $phone ?: null,
                'note' => $note,
                'status' => SellerRequestStatus::Waiting,
            ]);

            $sent[] = (string) $email;
        }

        if (count($lines) > self::MAX_ROWS) {
            $skipped[] = 'Only the first '.self::MAX_ROWS.' lines were read. Send the rest in another request.';
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }
}
