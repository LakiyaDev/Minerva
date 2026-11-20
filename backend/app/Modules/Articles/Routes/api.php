<?php

declare(strict_types=1);

use App\Modules\Articles\Http\Controllers\Api\V1\Admin\AdminArticleController;
use App\Modules\Articles\Http\Controllers\Api\V1\Public\PublicArticleAttachmentController;
use App\Modules\Articles\Http\Controllers\Api\V1\Public\PublicArticleController;
use App\Modules\Articles\Http\Controllers\Api\V1\Public\PublicCategoryController;
use App\Modules\Articles\Http\Controllers\Api\V1\Publisher\PublisherArticleAttachmentController;
use App\Modules\Articles\Http\Controllers\Api\V1\Publisher\PublisherArticleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Articles & Editorial routes (mounted under /api/v1)
|--------------------------------------------------------------------------
|
| Public catalogue and categories, the publisher workspace, and the
| administrative editorial orchestration endpoints. Every protected route
| re-checks permissions and ownership server-side; the frontend never decides
| access.
|
*/

Route::get('articles', [PublicArticleController::class, 'index'])
    ->middleware('throttle:articles-public');
Route::get('articles/{slug}', [PublicArticleController::class, 'show'])
    ->middleware('throttle:articles-public');
Route::get('articles/{article}/attachments/{attachment}/download', [PublicArticleAttachmentController::class, 'download'])
    ->middleware('throttle:articles-public');
Route::get('article-categories', [PublicCategoryController::class, 'index'])
    ->middleware('throttle:articles-public');

// Publisher workspace: own articles only, enforced by policy + service.
Route::prefix('publisher/articles')->middleware(['auth:sanctum', 'active'])->group(function (): void {
    Route::get('/', [PublisherArticleController::class, 'index'])
        ->middleware('permission:article.create');
    Route::post('/', [PublisherArticleController::class, 'store'])
        ->middleware('permission:article.create');
    Route::get('/{article}', [PublisherArticleController::class, 'show']);
    Route::patch('/{article}', [PublisherArticleController::class, 'update'])
        ->middleware('permission:article.update_own');
    Route::post('/{article}/publish', [PublisherArticleController::class, 'publish'])
        ->middleware('permission:article.publish_own');
    Route::post('/{article}/attachments', [PublisherArticleAttachmentController::class, 'store']);
    Route::delete('/{article}/attachments/{attachment}', [PublisherArticleAttachmentController::class, 'destroy']);
});

// Administrator article oversight (remove/unpublish, republish, feature).
Route::prefix('admin/articles')->middleware(['auth:sanctum', 'active', 'staff.mfa'])->group(function (): void {
    Route::get('/', [AdminArticleController::class, 'index'])
        ->middleware('permission:article.unpublish,article.publish_any');
    Route::post('/{article}/publish', [AdminArticleController::class, 'publish'])
        ->middleware('permission:article.publish_any');
    Route::post('/{article}/unpublish', [AdminArticleController::class, 'unpublish'])
        ->middleware('permission:article.unpublish');
    Route::post('/{article}/feature', [AdminArticleController::class, 'feature'])
        ->middleware('permission:article.feature');
});
