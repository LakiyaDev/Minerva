<?php

declare(strict_types=1);

namespace App\Modules\Voting\Http\Controllers\Api\V1\Admin;

use App\Modules\Voting\Http\Requests\StoreVotingPeriodRequest;
use App\Modules\Voting\Http\Requests\UpdateVotingPeriodRequest;
use App\Modules\Voting\Http\Resources\VotingPeriodResource;
use App\Modules\Voting\Models\VotingPeriod;
use App\Modules\Voting\Services\VotingPeriodService;
use App\Modules\Voting\Services\VotingQueryService;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminVotingPeriodController
{
    public function __construct(
        private readonly VotingPeriodService $periods,
        private readonly VotingQueryService $queries,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, $request->integer('per_page', 20)));

        $periods = VotingPeriod::query()
            ->orderByDesc('is_active')
            ->orderByDesc('starts_at')
            ->paginate($perPage);

        return ApiResponse::data(VotingPeriodResource::collection($periods));
    }

    public function store(StoreVotingPeriodRequest $request): JsonResponse
    {
        $period = $this->periods->create($request->user(), $request->validated(), $request);

        return ApiResponse::created(new VotingPeriodResource($period));
    }

    public function show(VotingPeriod $votingPeriod): JsonResponse
    {
        return ApiResponse::data(new VotingPeriodResource($votingPeriod));
    }

    public function update(UpdateVotingPeriodRequest $request, VotingPeriod $votingPeriod): JsonResponse
    {
        $updated = $this->periods->update($request->user(), $votingPeriod, $request->validated(), $request);

        return ApiResponse::data(new VotingPeriodResource($updated));
    }

    public function activate(Request $request, VotingPeriod $votingPeriod): JsonResponse
    {
        $activated = $this->periods->activate($request->user(), $votingPeriod, $request);

        return ApiResponse::data(new VotingPeriodResource($activated));
    }

    public function statistics(): JsonResponse
    {
        $stats = $this->queries->statistics();
        $stats['active_period'] = $stats['active_period'] !== null
            ? new VotingPeriodResource($stats['active_period'])
            : null;

        return ApiResponse::data($stats);
    }
}
