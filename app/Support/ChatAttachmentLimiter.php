<?php

namespace App\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Cache\RateLimiting\Unlimited;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class ChatAttachmentLimiter
{
    public function consume(Request $request): void
    {
        $limiter = RateLimiter::limiter('chat-attachments');

        if ($limiter === null) {
            return;
        }

        $limit = $limiter($request);

        if ($limit instanceof Unlimited) {
            return;
        }

        $key = (string) $limit->key;

        if (RateLimiter::tooManyAttempts($key, $limit->maxAttempts)) {
            abort(429);
        }

        RateLimiter::hit($key, $limit->decaySeconds);
    }

    public function limit(Request $request): Limit
    {
        return Limit::perHour(20)->by($this->key($request));
    }

    public function key(Request $request): string
    {
        return 'chat-attachments|'.($request->user()?->id ?: $request->ip());
    }
}
