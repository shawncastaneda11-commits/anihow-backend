<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\UserResource;
use Illuminate\Http\Request;

class AcceptTermsController extends Controller
{
    /**
     * Records that the signed-in user accepted the current Terms of Service
     * and Privacy Policy.
     */
    public function __invoke(Request $request): UserResource
    {
        $request->validate(['accept_terms' => ['accepted']]);

        $user = $request->user();
        $user->acceptCurrentTerms();

        return new UserResource($user->fresh()->load('roles', 'farm'));
    }
}
