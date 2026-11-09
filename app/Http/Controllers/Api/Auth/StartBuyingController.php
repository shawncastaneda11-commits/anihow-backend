<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\UserResource;
use App\Support\ActivityRecorder;
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

        if (! $user->isBuyer()) {
            $user->turnOnBuying('app');
            ActivityRecorder::note('buying_turned_on', $user, ['via' => 'app']);
        }

        return new UserResource($user->fresh()->load(['roles', 'farm']));
    }
}
