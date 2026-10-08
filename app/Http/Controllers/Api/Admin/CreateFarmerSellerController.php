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
        $created = $createFarmerSeller->handle($request->validated());

        return (new UserResource($created['user']))
            ->additional([
                'message' => 'Farmer-seller account created.',
                'temporary_password' => $created['temporary_password'],
            ])
            ->response()
            ->setStatusCode(201);
    }
}
