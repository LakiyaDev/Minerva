<?php

declare(strict_types=1);

use App\Modules\Voting\Http\Controllers\Api\V1\Admin\AdminVotingPeriodController;
use App\Modules\Voting\Http\Controllers\Api\V1\LeaderboardController;
use App\Modules\Voting\Http\Controllers\Api\V1\Me\MyVotesController;
use App\Modules\Voting\Http\Controllers\Api\V1\VoteController;
use App\Modules\Voting\Http\Controllers\Api\V1\VotingStatusController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Voting routes (mounted under /api/v1)
|--------------------------------------------------------------------------
|
| Server-enforced voting windows, irrevocable final votes, live participant
| results, and administrator voting-period orchestration.
|
*/

Route::get('voting/status', [VotingStatusController::class, 'show'])
    ->middleware('throttle:voting-read');

Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
    Route::post('articles/{article}/votes', [VoteController::class, 'store'])
        ->middleware(['permission:participant.vote', 'throttle:voting-cast']);

    Route::get('articles/{article}/vote-results', [VoteController::class, 'results'])
        ->middleware(['permission:participant.view_results', 'throttle:voting-read']);

    Route::get('me/votes', [MyVotesController::class, 'index'])
        ->middleware(['permission:participant.vote', 'throttle:voting-read']);

    Route::get('leaderboard', [LeaderboardController::class, 'index'])
        ->middleware(['permission:participant.view_results', 'throttle:voting-read']);
});

Route::prefix('admin')->middleware(['auth:sanctum', 'active', 'staff.mfa'])->group(function (): void {
    Route::get('voting-periods', [AdminVotingPeriodController::class, 'index'])
        ->middleware('permission:settings.manage');
    Route::post('voting-periods', [AdminVotingPeriodController::class, 'store'])
        ->middleware('permission:settings.manage');
    Route::get('voting-periods/{voting_period}', [AdminVotingPeriodController::class, 'show'])
        ->middleware('permission:settings.manage');
    Route::patch('voting-periods/{voting_period}', [AdminVotingPeriodController::class, 'update'])
        ->middleware('permission:settings.manage');
    Route::post('voting-periods/{voting_period}/activate', [AdminVotingPeriodController::class, 'activate'])
        ->middleware('permission:settings.manage');

    Route::get('voting/statistics', [AdminVotingPeriodController::class, 'statistics'])
        ->middleware('permission:settings.manage,analytics.view_all');
});
