<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Api\V1\Auth;

use App\Modules\Identity\Services\SessionService;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LogoutController
{
    public function __invoke(Request $request, SessionService $sessions): JsonResponse
    {
        $sessions->logout($request);

        return ApiResponse::message('Signed out.');
    }
}
