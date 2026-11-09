<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\UserResource;
use Illuminate\Http\Request;

/**
 * A farmer-seller turns on buying for their own account in the app. COSEL
 * can turn it on or off in the CMS. The account keeps one login.
 */
class StartBuyingController extends Controller
{
    public function __invoke(Request $request): UserResource
    {
        $user = $request->user();
        abort_unless($user->isFarmerSeller(), 403, 'Only a farmer-seller can turn on buying.');

        // Recorded on the account (buying_enabled_via = "app"); the activity
        // log holds what CMS staff do, so COSEL's own changes are logged.
        $user->turnOnBuying('app');

        return new UserResource($user->fresh()->load(['roles', 'farm']));
    }
}
