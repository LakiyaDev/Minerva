<?php

declare(strict_types=1);

use App\Modules\Comments\Http\Controllers\Api\V1\Admin\AdminCommentController;
use App\Modules\Comments\Http\Controllers\Api\V1\Participant\ParticipantCommentController;
use App\Modules\Comments\Http\Controllers\Api\V1\Public\PublicCommentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Comments & Moderation routes (mounted under /api/v1)
|--------------------------------------------------------------------------
*/

Route::get('articles/{article}/comments', [PublicCommentController::class, 'index'])
    ->middleware('throttle:comments-public');

Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
    Route::post('articles/{article}/comments', [ParticipantCommentController::class, 'store'])
        ->middleware(['permission:participant.comment', 'throttle:comments-write']);

    Route::delete('comments/{comment}', [ParticipantCommentController::class, 'destroy'])
        ->middleware('throttle:comments-write');

    Route::get('me/comments', [ParticipantCommentController::class, 'myComments'])
        ->middleware('throttle:comments-public');
});

Route::prefix('admin')->middleware(['auth:sanctum', 'active', 'staff.mfa'])->group(function (): void {
    Route::get('comments', [AdminCommentController::class, 'index'])
        ->middleware('permission:comment.moderate');

    Route::post('comments/{comment}/remove', [AdminCommentController::class, 'remove'])
        ->middleware('permission:comment.moderate');
});
