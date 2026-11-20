<?php

declare(strict_types=1);

namespace App\Modules\Articles\Http\Controllers\Api\V1\Public;

use App\Modules\Articles\Http\Resources\ArticleCategoryResource;
use App\Modules\Articles\Models\ArticleCategory;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublicCategoryController
{
    /**
     * Editorial categories with the number of publicly visible articles each
     * one currently holds.
     */
    public function index(Request $request): JsonResponse
    {
        $categories = ArticleCategory::query()
            ->withCount([
                'articles' => fn ($query) => $query->published(),
            ])
            ->orderBy('name')
            ->get();

        return ApiResponse::data(ArticleCategoryResource::collection($categories));
    }
}
