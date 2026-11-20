<?php

declare(strict_types=1);

namespace App\Modules\Voting\Http\Controllers\Api\V1;

use App\Modules\Voting\Services\VotingQueryService;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LeaderboardController
{
    public function __construct(private readonly VotingQueryService $queries) {}

    public function index(Request $request): JsonResponse
    {
        $board = $this->queries->leaderboard($request->user());

        return ApiResponse::data($board['entries'], [
            'total_votes' => $board['total_votes'],
            'updated_at' => now()->toIso8601String(),
        ]);
    }
}
