<?php

namespace App\Support;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\InvalidArgument;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;
use Throwable;

class FcmPush
{
    /**
     * @param  array<string, string>  $data
     * @return array{sent: int, failed: int}
     */
    public function send(User $user, string $channel, string $title, string $body, array $data): array
    {
        if (! $user->allowsPushCategory($channel)) {
            return ['sent' => 0, 'failed' => 0];
        }

        $tokens = $user->deviceTokens()->get();
        if ($tokens->isEmpty()) {
            return ['sent' => 0, 'failed' => 0];
        }

        if (! $this->configured()) {
            $this->noteMissingCredentials();

            return ['sent' => 0, 'failed' => $tokens->count()];
        }

        $sent = 0;
        $failed = 0;
        $messaging = app(Messaging::class);

        foreach ($tokens as $device) {
            $message = CloudMessage::fromArray([
                'token' => $device->token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $data,
                'android' => [
                    'priority' => 'high',
                    'notification' => [
                        'channel_id' => $channel,
                    ],
                ],
            ]);

            try {
                $messaging->send($message);
                $sent++;
            } catch (NotFound|InvalidArgument $exception) {
                $this->forgetToken($device, $exception);
                $failed++;
            } catch (MessagingException $exception) {
                if ($this->tokenWasRejected($exception)) {
                    $this->forgetToken($device, $exception);
                }
                $failed++;
            } catch (Throwable $exception) {
                report($exception);
                $failed++;
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    public function configured(): bool
    {
        $path = config('firebase.projects.app.credentials');

        return is_string($path) && $path !== '' && is_readable($path);
    }

    public function noteMissingCredentials(): void
    {
        $logged = Cache::add('fcm-unconfigured-notice', true, now()->addDay());
        if ($logged) {
            Log::info('FCM is not configured. Push notifications are off until FIREBASE_CREDENTIALS points at a readable key.');
        }
    }

    private function tokenWasRejected(MessagingException $exception): bool
    {
        if (! method_exists($exception, 'errors')) {
            return false;
        }

        $encoded = json_encode($exception->errors());

        return is_string($encoded)
            && (str_contains($encoded, 'UNREGISTERED') || str_contains($encoded, 'INVALID_ARGUMENT'));
    }

    private function forgetToken(DeviceToken $device, Throwable $exception): void
    {
        $device->delete();
        report($exception);
    }
}
