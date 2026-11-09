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
        $checked = $this->check($rows);

        foreach ($checked['people'] as $person) {
            // Checked again here: an earlier line of this batch may have
            // just asked for the same email.
            $problem = $person['problem'] ?? $this->problemWith((string) $person['email']);

            if ($problem !== null) {
                $skipped[] = "{$person['label']}: {$problem}";

                continue;
            }

            SellerRequest::query()->create([
                'farm_id' => $editor->farm_id,
                'requested_by' => $editor->getKey(),
                'name' => $person['name'],
                'email' => $person['email'],
                'phone' => $person['phone'] ?: null,
                'note' => $note,
                'status' => SellerRequestStatus::Waiting,
            ]);

            $sent[] = (string) $person['email'];
        }

        if ($checked['truncated']) {
            $skipped[] = 'Only the first '.self::MAX_ROWS.' lines were read. Send the rest in another request.';
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }

    /**
     * Reads the lines without sending anything, for "Check before sending".
     *
     * @return array{people: list<array{label: string, name: ?string, email: ?string, phone: ?string, problem: ?string}>, truncated: bool}
     */
    public function check(string $rows): array
    {
        $lines = array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', $rows) ?: []),
            fn (string $line): bool => $line !== '',
        ));
        $people = [];

        foreach (array_slice($lines, 0, self::MAX_ROWS) as $number => $line) {
            [$name, $email, $phone] = array_pad(array_map('trim', preg_split('/\t|,/', $line) ?: []), 3, null);
            $label = $name !== null && $name !== '' ? $name : 'Line '.($number + 1);

            if (strcasecmp((string) $name, 'name') === 0) {
                continue; // a header row copied with the data
            }

            $validation = Validator::make(
                ['name' => $name, 'email' => $email, 'phone' => $phone],
                [
                    'name' => ['required', 'string', 'max:255'],
                    'email' => ['required', 'email', 'max:255'],
                    'phone' => ['nullable', 'string', 'max:30'],
                ],
                ['email.required' => 'needs an email'],
            );

            $people[] = [
                'label' => $label,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'problem' => $validation->fails()
                    ? $validation->errors()->first()
                    : $this->problemWith((string) $email),
            ];
        }

        return ['people' => $people, 'truncated' => count($lines) > self::MAX_ROWS];
    }

    private function problemWith(string $email): ?string
    {
        if (User::withTrashed()->where('email', $email)->exists()) {
            return "{$email} already has an AniHow account.";
        }

        if (SellerRequest::query()->where('email', $email)->where('status', SellerRequestStatus::Waiting)->exists()) {
            return 'already waiting for COSEL.';
        }

        return null;
    }
}
