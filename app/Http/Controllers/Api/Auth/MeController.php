<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\UserResource;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __invoke(Request $request): UserResource
    {
        return new UserResource($request->user()->load('roles'));
    }
}
