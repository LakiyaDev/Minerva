<?php

declare(strict_types=1);

namespace App\Modules\Voting\Http\Controllers\Api\V1;

use App\Modules\Voting\Http\Requests\CastVoteRequest;
use App\Modules\Voting\Http\Resources\VoteResource;
use App\Modules\Voting\Services\VotingQueryService;
use App\Modules\Voting\Services\VotingTransactionService;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class VoteController
{
    public function __construct(
        private readonly VotingTransactionService $transactions,
        private readonly VotingQueryService $queries,
    ) {}

    public function store(CastVoteRequest $request, string $article): JsonResponse
    {
        $result = $this->transactions->castVote($request->user(), $article, $request);

        return ApiResponse::created([
            'vote' => new VoteResource($result['vote']),
            'article_id' => $result['vote']->article_id,
            'vote_count' => $result['vote_count'],
            'voting_period_id' => $result['period']->getKey(),
        ]);
    }

    public function results(Request $request, string $article): JsonResponse
    {
        $articleModel = $this->queries->resolvePublishedArticle($article);

        return ApiResponse::data(
            $this->queries->articleResults($articleModel, $request->user()),
        );
    }
}
