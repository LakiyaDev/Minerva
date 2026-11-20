<?php

declare(strict_types=1);

use App\Modules\Analytics\Http\Controllers\Api\V1\Admin\AdminAnalyticsController;
use App\Modules\Analytics\Http\Controllers\Api\V1\Publisher\PublisherArticleAnalyticsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Analytics routes (mounted under /api/v1)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
    Route::get('publisher/articles/{article}/analytics', [PublisherArticleAnalyticsController::class, 'show'])
        ->middleware('permission:analytics.view_own');
});

Route::prefix('admin')->middleware(['auth:sanctum', 'active', 'staff.mfa'])->group(function (): void {
    Route::get('analytics', [AdminAnalyticsController::class, 'index'])
        ->middleware('permission:analytics.view_all');
});
