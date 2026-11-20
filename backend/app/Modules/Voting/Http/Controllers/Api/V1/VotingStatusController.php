<?php

declare(strict_types=1);

namespace App\Modules\Voting\Http\Controllers\Api\V1;

use App\Modules\Voting\Http\Resources\VotingPeriodResource;
use App\Modules\Voting\Services\VotingPeriodService;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

final class VotingStatusController
{
    public function __construct(private readonly VotingPeriodService $periods) {}

    public function show(): JsonResponse
    {
        $now = now();
        $period = $this->periods->current();

        return ApiResponse::data([
            'status' => $period?->windowStatus($now) ?? 'closed',
            'is_open' => $period?->isOpen($now) ?? false,
            'server_time' => $now->toIso8601String(),
            'period' => $period !== null ? new VotingPeriodResource($period) : null,
        ]);
    }
}
