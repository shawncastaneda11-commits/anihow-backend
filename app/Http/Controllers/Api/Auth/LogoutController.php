<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class LogoutController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_token' => ['sometimes', 'nullable', 'string', 'max:512'],
        ]);

        $deviceToken = $validated['device_token'] ?? null;
        if (is_string($deviceToken) && $deviceToken !== '') {
            $request->user()?->deviceTokens()->where('token', $deviceToken)->delete();
        }

        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Logged out.',
        ]);
    }
}
