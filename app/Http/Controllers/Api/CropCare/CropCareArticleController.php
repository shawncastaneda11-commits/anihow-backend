<?php

namespace App\Http\Controllers\Api\CropCare;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CropCare\CropCareIndexRequest;
use App\Http\Resources\Api\CropCareArticleResource;
use App\Models\CropCareArticle;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CropCareArticleController extends Controller
{
    public function index(CropCareIndexRequest $request): AnonymousResourceCollection
    {
        $articles = CropCareArticle::query()
            ->active()
            ->with('category')
            ->when(
                $request->validated('category_id'),
                fn ($query, $categoryId) => $query->where('category_id', $categoryId),
            )
            ->when(
                $request->validated('search'),
                fn ($query, string $search) => $query->where(function ($query) use ($search): void {
                    $query->where('title', 'like', '%'.$search.'%')
                        ->orWhere('body', 'like', '%'.$search.'%');
                }),
            )
            ->latest()
            ->paginate();

        return CropCareArticleResource::collection($articles);
    }

    public function show(CropCareArticle $cropCareArticle): CropCareArticleResource
    {
        abort_unless($cropCareArticle->is_active, 404);

        $this->authorize('view', $cropCareArticle);

        $cropCareArticle->load('category');

        return new CropCareArticleResource($cropCareArticle);
    }
}
