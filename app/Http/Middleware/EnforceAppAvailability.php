<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use App\Support\AppVersion;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The app's side of ICTD's switches.
 *
 * Maintenance: every API call answers 503 with ICTD's message, so the app can
 * show it instead of failing in odd ways. The CMS is not affected.
 *
 * Minimum version: an app that sends X-App-Version older than the minimum is
 * answered 426 with an update message. Apps that send no version (1.0.2 and
 * older) are let through.
 *
 * The app's own config call is always answered, so it can show either screen.
 */
class EnforceAppAvailability
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/app/config')) {
            return $next($request);
        }

        $maintenance = SystemSetting::maintenanceMessage();

        if ($maintenance !== null) {
            return response()->json([
                'message' => $maintenance,
                'maintenance' => true,
            ], 503);
        }

        $minimum = SystemSetting::minimumAppVersion();

        if (AppVersion::isOlder($request->header('X-App-Version'), $minimum)) {
            return response()->json([
                'message' => "Please update AniHow to version {$minimum} or newer to keep using it.",
                'update_required' => true,
                'min_version' => $minimum,
                'download_url' => SystemSetting::appDownloadUrl(),
            ], 426);
        }

        return $next($request);
    }
}
