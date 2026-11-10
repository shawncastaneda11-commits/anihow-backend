<?php

namespace App\Http\Controllers\Api\Me;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class DeviceTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        $signIn = $request->user()->currentAccessToken();

        // Tied to this sign-in: when it ends, this phone stops getting pushes.
        $device = DeviceToken::query()->updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => $request->user()->id,
                'personal_access_token_id' => $signIn instanceof PersonalAccessToken ? $signIn->getKey() : null,
                'platform' => 'android',
                'last_seen_at' => now(),
            ],
        );

        return response()->json([
            'data' => [
                'id' => $device->id,
                'platform' => $device->platform,
                'last_seen_at' => $device->last_seen_at,
            ],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        $request->user()
            ->deviceTokens()
            ->where('token', $validated['token'])
            ->delete();

        return response()->json([
            'message' => 'Device token removed.',
        ]);
    }
}
