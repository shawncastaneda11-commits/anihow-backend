<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\ChangePasswordRequest;
use Illuminate\Http\JsonResponse;
use Laravel\Sanctum\PersonalAccessToken;

class ChangePasswordController extends Controller
{
    public function __invoke(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update([
            'password' => $request->validated('password'),
        ]);

        // Anyone else signed in with the old password is signed out; this
        // phone stays signed in. Their push registrations end with them.
        $current = $user->currentAccessToken();
        $others = $user->tokens()->when(
            $current instanceof PersonalAccessToken,
            fn ($tokens) => $tokens->whereKeyNot($current->getKey()),
        )->delete();

        return response()->json([
            'message' => $others > 0
                ? 'Password updated. Your other phones were signed out.'
                : 'Password updated.',
        ]);
    }
}
