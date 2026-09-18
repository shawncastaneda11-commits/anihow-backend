<?php

namespace App\Http\Controllers\Api\Reviews;

use App\Actions\Reviews\CreateReviewAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Reviews\StoreReviewRequest;
use App\Http\Resources\Api\ReviewResource;
use Illuminate\Http\JsonResponse;

class ReviewController extends Controller
{
    public function store(StoreReviewRequest $request, CreateReviewAction $createReview): JsonResponse
    {
        $review = $createReview->handle(
            $request->user(),
            $request->reservation(),
            (int) $request->validated('rating'),
            $request->validated('comment'),
        );

        return (new ReviewResource($review))
            ->additional(['message' => 'Review saved.'])
            ->response()
            ->setStatusCode(201);
    }
}
