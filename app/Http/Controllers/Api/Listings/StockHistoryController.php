<?php

namespace App\Http\Controllers\Api\Listings;

use App\Actions\Listings\StockHistory;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockHistoryController extends Controller
{
    public function show(Request $request, Listing $listing, StockHistory $history): JsonResponse
    {
        $this->authorize('update', $listing);

        $page = max(1, (int) $request->integer('page', 1));
        $result = $history->forListing($listing, $page);

        return response()->json([
            'data' => $result['items']->items(),
            'summary' => $result['summary'],
            'meta' => [
                'current_page' => $result['items']->currentPage(),
                'last_page' => $result['items']->lastPage(),
                'per_page' => $result['items']->perPage(),
                'total' => $result['items']->total(),
            ],
        ]);
    }
}
