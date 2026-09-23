<?php

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Auth\SendEmailVerificationCodeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\VerifyEmailRequest;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class VerifyEmailController extends Controller
{
    public function __invoke(VerifyEmailRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email is already verified.',
            ]);
        }

        $cacheKey = SendEmailVerificationCodeAction::CACHE_PREFIX.$user->getKey();
        $hashed = Cache::get($cacheKey);

        if (! is_string($hashed) || ! Hash::check($request->validated('code'), $hashed)) {
            throw ValidationException::withMessages([
                'code' => 'The verification code is invalid or has expired.',
            ]);
        }

        $user->markEmailAsVerified();
        event(new Verified($user));
        Cache::forget($cacheKey);

        return response()->json([
            'message' => 'Email verified.',
        ]);
    }
}
