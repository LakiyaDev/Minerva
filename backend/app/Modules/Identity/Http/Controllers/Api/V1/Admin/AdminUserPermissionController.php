<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Api\V1\Admin;

use App\Modules\Identity\Http\Requests\GrantPermissionRequest;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\PermissionGrantService;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

final class AdminUserPermissionController
{
    public function store(
        GrantPermissionRequest $request,
        User $user,
        PermissionGrantService $permissions,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $expiresAt = $request->filled('expires_at')
            ? Carbon::parse((string) $request->string('expires_at'))
            : null;

        $grant = $permissions->grant(
            $user,
            (string) $request->string('permission'),
            $actor,
            $expiresAt,
            $request,
        );

        return ApiResponse::created([
            'user_id' => $user->getKey(),
            'permission' => $grant->permission->name,
            'expires_at' => $grant->expires_at?->toIso8601String(),
        ]);
    }

    public function destroy(
        GrantPermissionRequest $request,
        User $user,
        PermissionGrantService $permissions,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $permissions->revoke($user, (string) $request->string('permission'), $actor, $request);

        return ApiResponse::message('Permission revoked.');
    }
}
