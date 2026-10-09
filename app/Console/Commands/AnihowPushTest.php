<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\FcmPush;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('anihow:push-test {email}')]
#[Description('Send one privacy-safe test push to an account')]
class AnihowPushTest extends Command
{
    public function handle(FcmPush $push): int
    {
        $email = (string) $this->argument('email');
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error('No account uses that email.');

            return self::FAILURE;
        }

        $result = $push->send($user, 'general', 'AniHow', 'Test notification.', [
            'notification_id' => '',
            'type' => 'test',
            'related_type' => '',
            'related_id' => '',
        ]);

        $this->info("Sent to {$result['sent']} devices. Failed: {$result['failed']}.");

        return self::SUCCESS;
    }
}
