<?php

declare(strict_types=1);

namespace App\Modules\Articles\Http\Controllers\Api\V1\Publisher;

use App\Modules\Articles\Enums\ArticleStatus;
use App\Modules\Articles\Http\Requests\StoreArticleDraftRequest;
use App\Modules\Articles\Http\Requests\UpdateArticleRequest;
use App\Modules\Articles\Http\Resources\ArticleDetailResource;
use App\Modules\Articles\Http\Resources\ArticleListResource;
use App\Modules\Articles\Models\Article;
use App\Modules\Articles\Services\ArticleWorkflowService;
use App\Modules\Identity\Models\User;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class PublisherArticleController
{
    public function __construct(private readonly ArticleWorkflowService $workflow) {}

    /**
     * The publisher's own workspace. Analytics isolation is enforced by the
     * author_id predicate itself; no cross-article data is ever returned.
     */
    public function index(Request $request): JsonResponse
    {
        $status = ArticleStatus::tryFrom((string) $request->input('status', ''));

        /** @var User $actor */
        $actor = $request->user();

        $perPage = min(100, max(1, $request->integer('per_page', 20)));

        $articles = Article::query()
            ->where('author_id', $actor->getKey())
            ->with(['category:id,name,slug', 'author:id,name'])
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->orderByDesc('updated_at')
            ->paginate($perPage);

        return ApiResponse::data(ArticleListResource::collection($articles));
    }

    public function store(StoreArticleDraftRequest $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $article = $this->workflow->createDraft($actor, $request->validated());

        return ApiResponse::created(new ArticleDetailResource($article->loadMissing('category', 'attachments')));
    }

    public function show(Request $request, Article $article): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        Gate::forUser($actor)->authorize('viewOwn', $article);

        return ApiResponse::data(new ArticleDetailResource(
            $article->loadMissing(['category', 'author', 'attachments', 'statusHistory']),
        ));
    }

    public function update(UpdateArticleRequest $request, Article $article): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        Gate::forUser($actor)->authorize('updateOwn', $article);

        $article = $this->workflow->updateDraft($actor, $article, $request->validated());

        return ApiResponse::data(new ArticleDetailResource(
            $article->loadMissing(['category', 'author', 'attachments']),
        ));
    }

    public function submit(Request $request, Article $article): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        Gate::forUser($actor)->authorize('submit', $article);

        $article = $this->workflow->transition($article, ArticleStatus::PendingReview, $actor);

        return ApiResponse::data(new ArticleDetailResource(
            $article->loadMissing(['category', 'author', 'attachments']),
        ));
    }

    public function publish(Request $request, Article $article): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        Gate::forUser($actor)->authorize('publishOwn', $article);

        $article = $this->workflow->transition($article, ArticleStatus::Published, $actor);

        return ApiResponse::data(new ArticleDetailResource(
            $article->loadMissing(['category', 'author', 'attachments']),
        ));
    }
}
