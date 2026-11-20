<?php

declare(strict_types=1);

namespace App\Modules\Articles\Http\Controllers\Api\V1\Public;

use App\Modules\Articles\Http\Resources\ArticleDetailResource;
use App\Modules\Articles\Http\Resources\ArticleListResource;
use App\Modules\Articles\Models\Article;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublicArticleController
{
    /**
     * Public catalogue. Only published articles are ever returned; filters
     * use model scopes so client input cannot reach the query directly.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $this->perPage($request);

        $articles = Article::query()
            ->published()
            ->with(['category:id,name,slug', 'author:id,name'])
            ->search($request->input('search'))
            ->category($request->input('category'))
            ->sort($request->input('sort'))
            ->paginate($perPage);

        return ApiResponse::data(ArticleListResource::collection($articles));
    }

    /**
     * Public article detail, resolved by slug (or opaque id) and only when
     * published. A draft, pending or unpublished article is a 404.
     */
    public function show(string $slug): JsonResponse
    {
        $article = Article::query()
            ->published()
            ->with([
                'category:id,name,slug',
                'author:id,name',
                'attachments',
                'statusHistory',
            ])
            ->where(function (Builder $query) use ($slug): void {
                $query->where('slug', $slug)->orWhere('id', $slug);
            })
            ->firstOrFail();

        return ApiResponse::data(new ArticleDetailResource($article));
    }

    private function perPage(Request $request): int
    {
        $perPage = $request->integer('per_page', 12);

        if ($perPage < 1) {
            return 12;
        }

        return min(100, $perPage);
    }
}
