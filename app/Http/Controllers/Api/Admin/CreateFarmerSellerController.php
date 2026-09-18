<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Admin\CreateFarmerSellerAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\StoreFarmerSellerRequest;
use App\Http\Resources\Api\UserResource;
use Illuminate\Http\JsonResponse;

class CreateFarmerSellerController extends Controller
{
    public function __invoke(StoreFarmerSellerRequest $request, CreateFarmerSellerAction $createFarmerSeller): JsonResponse
    {
        $user = $createFarmerSeller->handle($request->validated());

        return (new UserResource($user))
            ->additional([
                'message' => 'Farmer-seller account created.',
            ])
            ->response()
            ->setStatusCode(201);
    }
}
