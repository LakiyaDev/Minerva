<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Http\Controllers\Api\V1\Admin;

use App\Modules\Analytics\Http\Resources\AdminAnalyticsResource;
use App\Modules\Analytics\Policies\AnalyticsPolicy;
use App\Modules\Analytics\Services\AnalyticsService;
use App\Modules\Identity\Models\User;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class AdminAnalyticsController
{
    public function __construct(private readonly AnalyticsService $analytics) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        Gate::forUser($actor)->authorize('viewPlatformAnalytics', AnalyticsPolicy::class);

        $data = $this->analytics->getPlatformAnalytics();

        return ApiResponse::data(new AdminAnalyticsResource($data));
    }
}
