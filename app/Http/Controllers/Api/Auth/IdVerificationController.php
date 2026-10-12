<?php

namespace App\Http\Controllers\Api\Auth;

use App\Actions\IdVerification\SubmitIdVerificationAction;
use App\Enums\IdDocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\StoreIdVerificationRequest;
use App\Http\Resources\Api\UserResource;
use Illuminate\Http\JsonResponse;

class IdVerificationController extends Controller
{
    /**
     * A buyer sends a photo of their ID (and, optionally, a selfie holding
     * it) for an admin to review.
     */
    public function store(StoreIdVerificationRequest $request, SubmitIdVerificationAction $submit): JsonResponse
    {
        $user = $request->user();

        $submit->handle(
            $user,
            IdDocumentType::from($request->validated('id_type')),
            $request->file('front'),
            $request->file('selfie'),
        );

        return (new UserResource($user->fresh()->load('roles', 'farm')))
            ->response()
            ->setStatusCode(201);
    }
}
