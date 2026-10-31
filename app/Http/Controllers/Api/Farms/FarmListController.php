<?php

namespace App\Http\Controllers\Api\Farms;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use Illuminate\Http\JsonResponse;

class FarmListController extends Controller
{
    /**
     * The active partner farms, for the marketplace Farm filter.
     */
    public function __invoke(): JsonResponse
    {
        $farms = Farm::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Farm $farm): array => ['id' => $farm->id, 'name' => $farm->name]);

        return response()->json(['data' => $farms]);
    }
}
