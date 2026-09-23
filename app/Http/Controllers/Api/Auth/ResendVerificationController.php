<?php

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Auth\SendEmailVerificationCodeAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResendVerificationController extends Controller
{
    public function __invoke(Request $request, SendEmailVerificationCodeAction $sendCode): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email is already verified.',
            ]);
        }

        $code = $sendCode->handle($request->user());
        $payload = [
            'message' => 'Verification code sent.',
        ];

        if (SendEmailVerificationCodeAction::shouldExposeCode()) {
            $payload['verification_code'] = $code;
        }

        return response()->json($payload);
    }
}
