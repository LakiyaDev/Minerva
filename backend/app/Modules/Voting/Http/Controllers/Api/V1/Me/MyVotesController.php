<?php

declare(strict_types=1);

namespace App\Modules\Voting\Http\Controllers\Api\V1\Me;

use App\Modules\Voting\Http\Resources\VoteResource;
use App\Modules\Voting\Services\VotingQueryService;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MyVotesController
{
    public function __construct(private readonly VotingQueryService $queries) {}

    public function index(Request $request): JsonResponse
    {
        $votes = $this->queries->userVotes($request->user());

        return ApiResponse::data(
            VoteResource::collection($votes),
            ['total' => $votes->count()],
        );
    }
}
