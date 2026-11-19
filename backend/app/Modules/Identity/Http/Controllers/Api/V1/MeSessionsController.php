<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Api\V1;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\SessionService;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MeSessionsController
{
    public function __invoke(Request $request, SessionService $sessions): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $revoked = $sessions->revokeOtherSessions($user, $request);

        return ApiResponse::data([
            'revoked_sessions' => $revoked,
        ]);
    }
}
