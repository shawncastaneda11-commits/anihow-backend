<?php

namespace App\Http\Controllers\Api\CropCare;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CropCare\CropCareIndexRequest;
use App\Http\Resources\Api\CropCareArticleResource;
use App\Models\CropCareArticle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read-only in the app. Crop-care content is written by each farm's Content
 * Editor in the CMS; farmer-sellers and buyers read it and nothing more.
 *
 * The write endpoints that used to live here belonged to the farmer-seller
 * and were removed with the rebuild, not ported.
 */
class CropCareArticleController extends Controller
{
    public function index(CropCareIndexRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CropCareArticle::class);

        $articles = CropCareArticle::query()
            ->published()
            ->with(['farm', 'author', 'cropTypes'])
            ->when(
                $request->validated('crop_type_id'),
                fn (Builder $query, $cropTypeId) => $query->whereHas(
                    'cropTypes',
                    fn (Builder $cropType): Builder => $cropType->whereKey($cropTypeId),
                ),
            )
            ->when(
                $request->validated('farm_id'),
                fn (Builder $query, $farmId) => $query->where('farm_id', $farmId),
            )
            ->when(
                $request->validated('category'),
                fn (Builder $query, string $category) => $query->where('category', $category),
            )
            ->when(
                $request->validated('search'),
                fn (Builder $query, string $search) => $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', '%'.$search.'%')
                        ->orWhere('body', 'like', '%'.$search.'%');
                }),
            )
            ->latest('published_at')
            ->orderByDesc('id')
            ->paginate();

        return CropCareArticleResource::collection($articles);
    }

    public function show(CropCareArticle $cropCareArticle): CropCareArticleResource
    {
        $this->authorize('view', $cropCareArticle);

        abort_unless($cropCareArticle->isPublished(), 404);

        $cropCareArticle->load(['farm', 'author', 'cropTypes']);

        return new CropCareArticleResource($cropCareArticle);
    }
}
