<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\SellerHelpResource;
use App\Models\Farm;

class SellerHelpController extends Controller
{
    public function __invoke(): SellerHelpResource
    {
        $farms = Farm::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'municipality', 'contact_person', 'contact_number']);

        return new SellerHelpResource($farms);
    }
}
