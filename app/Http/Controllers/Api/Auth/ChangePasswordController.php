<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\ChangePasswordRequest;
use Illuminate\Http\JsonResponse;

class ChangePasswordController extends Controller
{
    public function __invoke(ChangePasswordRequest $request): JsonResponse
    {
        $request->user()->update([
            'password' => $request->validated('password'),
        ]);

        return response()->json([
            'message' => 'Password updated.',
        ]);
    }
}
