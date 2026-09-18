<?php

namespace App\Http\Controllers\Api\CropCare;

use App\Actions\CropCare\CreateCropCareArticleAction;
use App\Actions\CropCare\UpdateCropCareArticleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CropCare\CropCareIndexRequest;
use App\Http\Requests\Api\CropCare\StoreCropCareArticleRequest;
use App\Http\Requests\Api\CropCare\UpdateCropCareArticleRequest;
use App\Http\Resources\Api\CropCareArticleResource;
use App\Http\Resources\Api\CropCareCategoryResource;
use App\Models\Category;
use App\Models\CropCareArticle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CropCareArticleController extends Controller
{
    public function categories(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CropCareArticle::class);

        $categories = Category::query()
            ->whereHas('cropCareArticles', fn ($query) => $query->active())
            ->withCount([
                'cropCareArticles as tips_count' => fn ($query) => $query->active(),
            ])
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'tips_count' => $category->tips_count,
            ]);

        $uncategorizedCount = CropCareArticle::query()
            ->active()
            ->whereNull('category_id')
            ->count();

        if ($uncategorizedCount > 0) {
            $categories->push([
                'id' => CropCareArticle::GENERAL_CATEGORY_ID,
                'name' => 'General',
                'slug' => 'general',
                'tips_count' => $uncategorizedCount,
            ]);
        }

        return CropCareCategoryResource::collection($categories->values());
    }

    public function mine(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CropCareArticle::class);

        $articles = $request->user()
            ->cropCareArticles()
            ->with(['category', 'author'])
            ->latest()
            ->orderByDesc('id')
            ->paginate();

        return CropCareArticleResource::collection($articles);
    }

    public function index(CropCareIndexRequest $request): AnonymousResourceCollection
    {
        $articles = CropCareArticle::query()
            ->active()
            ->with(['category', 'author'])
            ->when(
                $request->has('category_id'),
                function ($query) use ($request) {
                    $categoryId = (int) $request->validated('category_id');

                    if ($categoryId === CropCareArticle::GENERAL_CATEGORY_ID) {
                        return $query->whereNull('category_id');
                    }

                    return $query->where('category_id', $categoryId);
                },
            )
            ->when(
                $request->validated('search'),
                fn ($query, string $search) => $query->where(function ($query) use ($search): void {
                    $query->where('title', 'like', '%'.$search.'%')
                        ->orWhere('body', 'like', '%'.$search.'%');
                }),
            )
            ->latest()
            ->orderByDesc('id')
            ->paginate();

        return CropCareArticleResource::collection($articles);
    }

    public function store(StoreCropCareArticleRequest $request, CreateCropCareArticleAction $createArticle): JsonResponse
    {
        $article = $createArticle->handle(
            $request->user(),
            $request->safe()->only(['title', 'body', 'category_id']),
        );

        return (new CropCareArticleResource($article))
            ->additional(['message' => 'Guide created.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, CropCareArticle $cropCareArticle): CropCareArticleResource
    {
        if (! $cropCareArticle->is_active && ! $cropCareArticle->isOwnedBy($request->user())) {
            abort(404);
        }

        $this->authorize('view', $cropCareArticle);

        $cropCareArticle->load(['category', 'author']);

        return new CropCareArticleResource($cropCareArticle);
    }

    public function update(
        UpdateCropCareArticleRequest $request,
        CropCareArticle $cropCareArticle,
        UpdateCropCareArticleAction $updateArticle,
    ): CropCareArticleResource {
        $article = $updateArticle->handle(
            $cropCareArticle,
            $request->safe()->only(['title', 'body', 'category_id']),
        );

        return (new CropCareArticleResource($article))
            ->additional(['message' => 'Guide updated.']);
    }

    public function destroy(CropCareArticle $cropCareArticle): JsonResponse
    {
        $this->authorize('delete', $cropCareArticle);

        $cropCareArticle->delete();

        return response()->json([
            'message' => 'Guide deleted.',
        ]);
    }
}
