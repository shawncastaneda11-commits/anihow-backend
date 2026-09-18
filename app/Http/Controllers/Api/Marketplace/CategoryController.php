<?php

namespace App\Http\Controllers\Api\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        $categories = Category::query()
            ->active()
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($categories);
    }
}
