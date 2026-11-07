<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\AppVersion;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Notes which app version a signed-in user is on, for ICTD's system health
 * page. Written at most once an hour per user, or when the version changes.
 */
class RecordAppVersion
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $version = AppVersion::normalize($request->header('X-App-Version'));
        $user = $request->user();

        if ($version !== null && $user instanceof User) {
            $stale = $user->last_app_seen_at === null || $user->last_app_seen_at->lt(now()->subHour());

            if ($stale || $user->last_app_version !== $version) {
                User::query()->whereKey($user->getKey())->update([
                    'last_app_version' => $version,
                    'last_app_seen_at' => now(),
                ]);
            }
        }

        return $response;
    }
}
