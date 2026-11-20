<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Http\Controllers\Api\V1\Publisher;

use App\Modules\Analytics\Http\Resources\PublisherArticleAnalyticsResource;
use App\Modules\Analytics\Policies\AnalyticsPolicy;
use App\Modules\Analytics\Services\AnalyticsService;
use App\Modules\Articles\Models\Article;
use App\Modules\Identity\Models\User;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class PublisherArticleAnalyticsController
{
    public function __construct(private readonly AnalyticsService $analytics) {}

    public function show(Request $request, Article $article): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        Gate::forUser($actor)->authorize('viewArticleAnalytics', [AnalyticsPolicy::class, $article]);

        $data = $this->analytics->getArticleAnalytics($article);

        return ApiResponse::data(new PublisherArticleAnalyticsResource($data));
    }
}
