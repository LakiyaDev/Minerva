<?php

declare(strict_types=1);

namespace App\Modules\Articles\Http\Controllers\Api\V1\Admin;

use App\Modules\Articles\Enums\ArticleStatus;
use App\Modules\Articles\Http\Requests\FeatureArticleRequest;
use App\Modules\Articles\Http\Resources\ArticleDetailResource;
use App\Modules\Articles\Http\Resources\ArticleListResource;
use App\Modules\Articles\Models\Article;
use App\Modules\Articles\Services\ArticleWorkflowService;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminArticleController
{
    public function __construct(private readonly ArticleWorkflowService $workflow) {}

    public function index(Request $request): JsonResponse
    {
        $status = ArticleStatus::tryFrom((string) $request->input('status', ''));
        $perPage = min(100, max(1, $request->integer('per_page', 20)));

        $articles = Article::query()
            ->with(['category:id,name,slug', 'author:id,name'])
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->search($request->input('search'))
            ->category($request->input('category'))
            ->orderByDesc('updated_at')
            ->paginate($perPage);

        return ApiResponse::data(ArticleListResource::collection($articles));
    }

    public function publish(Request $request, Article $article): JsonResponse
    {
        $article = $this->workflow->transition($article, ArticleStatus::Published, $request->user());

        return $this->detail($article);
    }

    public function unpublish(Request $request, Article $article): JsonResponse
    {
        $article = $this->workflow->transition($article, ArticleStatus::Unpublished, $request->user());

        return $this->detail($article);
    }

    public function feature(FeatureArticleRequest $request, Article $article): JsonResponse
    {
        $article = $this->workflow->setFeatured(
            $request->user(),
            $article,
            (bool) $request->validated()['is_featured'],
        );

        return $this->detail($article);
    }

    private function detail(Article $article): JsonResponse
    {
        return ApiResponse::data(new ArticleDetailResource(
            $article->loadMissing(['category', 'author', 'attachments', 'statusHistory']),
        ));
    }
}
