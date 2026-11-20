<?php

declare(strict_types=1);

namespace App\Modules\Audit\Http\Controllers\Api\V1\Admin;

use App\Modules\Audit\Http\Resources\AuditLogResource;
use App\Modules\Audit\Services\AuditQueryService;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminAuditLogController
{
    public function __construct(private readonly AuditQueryService $queries) {}

    public function index(Request $request): JsonResponse
    {
        $logs = $this->queries->paginate($request);

        return ApiResponse::data(AuditLogResource::collection($logs));
    }
}
