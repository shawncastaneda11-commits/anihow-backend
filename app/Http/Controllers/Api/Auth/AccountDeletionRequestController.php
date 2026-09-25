<?php

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Privacy\CancelAccountDeletionRequestAction;
use App\Actions\Privacy\RequestAccountDeletionAction;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\StoreAccountDeletionRequest;
use App\Http\Resources\Api\AccountDeletionRequestResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountDeletionRequestController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can(Permission::RequestAccountDeletion->value), 403);

        $latest = $request->user()
            ->accountDeletionRequests()
            ->latest()
            ->first();

        return response()->json([
            'data' => $latest === null ? null : (new AccountDeletionRequestResource($latest))->resolve(),
        ]);
    }

    public function store(
        StoreAccountDeletionRequest $request,
        RequestAccountDeletionAction $create,
    ): JsonResponse {
        $record = $create->handle($request->user(), $request->validated('reason'));

        return (new AccountDeletionRequestResource($record))
            ->additional(['message' => 'Account deletion requested.'])
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Request $request, CancelAccountDeletionRequestAction $cancel): JsonResponse
    {
        abort_unless($request->user()?->can(Permission::RequestAccountDeletion->value), 403);

        $cancel->handle($request->user());

        return response()->json([
            'message' => 'Account deletion request cancelled.',
        ]);
    }
}
