<?php

namespace App\Http\Controllers\Api\Reviews;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Reviews\StoreReviewRequest;
use App\Http\Resources\Api\ReviewResource;
use App\Models\Review;
use Illuminate\Http\JsonResponse;

class ReviewController extends Controller
{
    /**
     * A review is tied to one completed order. No order, no review.
     * The order check lives in StoreReviewRequest so it runs before this.
     */
    public function store(StoreReviewRequest $request): JsonResponse
    {
        $order = $request->order();

        $review = Review::create([
            'order_id' => $order->id,
            'buyer_id' => $request->user()->id,
            'farmer_seller_id' => $order->farmer_seller_id,
            'rating' => (int) $request->validated('rating'),
            'comment' => $request->validated('comment'),
        ]);

        return (new ReviewResource($review->load(['buyer', 'order'])))
            ->additional(['message' => 'Review saved.'])
            ->response()
            ->setStatusCode(201);
    }
}
