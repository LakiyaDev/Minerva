<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Api\V1\Auth;

use App\Modules\Identity\Http\Requests\StaffLoginRequest;
use App\Modules\Identity\Http\Resources\MeResource;
use App\Modules\Identity\Services\SessionService;
use App\Modules\Identity\Services\StaffAuthenticationService;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

final class StaffLoginController
{
    public function __invoke(
        StaffLoginRequest $request,
        StaffAuthenticationService $authentication,
        SessionService $sessions,
    ): JsonResponse {
        $result = $authentication->attempt(
            (string) $request->string('email'),
            (string) $request->string('password'),
            $request,
        );

        if ($result['status'] === 'authenticated') {
            $sessions->login($result['user'], $request, 'staff');

            return ApiResponse::data([
                'status' => 'authenticated',
                'user' => new MeResource($result['user']->load('roles')),
            ]);
        }

        return ApiResponse::data([
            'status' => $result['status'],
            'challenge_token' => $result['token'],
            'expires_in' => (int) config('identity.staff.challenge_ttl_seconds', 300),
        ], status: 202);
    }
}
