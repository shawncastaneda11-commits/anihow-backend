<?php

namespace App\Http\Controllers\Api\Pos;

use App\Actions\Pos\RecordPosSaleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Pos\StoreSaleRequest;
use App\Http\Resources\Api\SaleResource;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SaleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Sale::class);

        $sales = $request->user()
            ->sales()
            ->with('items')
            ->latest()
            ->paginate();

        return SaleResource::collection($sales);
    }

    public function store(StoreSaleRequest $request, RecordPosSaleAction $recordPosSale): JsonResponse
    {
        $sale = $recordPosSale->handle(
            $request->user(),
            $request->items(),
            $request->validated('notes'),
        );

        return (new SaleResource($sale))
            ->additional(['message' => 'Walk-in sale recorded.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Sale $sale): SaleResource
    {
        $this->authorize('view', $sale);

        $sale->load('items');

        return new SaleResource($sale);
    }
}
