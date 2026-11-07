<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;

/**
 * What the app checks when it opens: the minimum version and whether AniHow
 * is under maintenance. Public, so it works before sign-in.
 */
class AppConfigController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $maintenance = SystemSetting::maintenanceMessage();

        return response()->json([
            'min_version' => SystemSetting::minimumAppVersion(),
            'maintenance' => $maintenance !== null,
            'maintenance_message' => $maintenance,
        ]);
    }
}
