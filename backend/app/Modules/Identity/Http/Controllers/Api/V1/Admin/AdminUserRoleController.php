<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Api\V1\Admin;

use App\Modules\Identity\Http\Requests\AssignRoleRequest;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\RoleService;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminUserRoleController
{
    public function store(
        AssignRoleRequest $request,
        User $user,
        RoleService $roles,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $roles->assign($user, (string) $request->string('role'), $actor, $request);

        return $this->rolesResponse($user);
    }

    public function destroy(
        AssignRoleRequest $request,
        User $user,
        RoleService $roles,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $roles->revoke($user, (string) $request->string('role'), $actor, $request);

        return $this->rolesResponse($user);
    }

    private function rolesResponse(User $user): JsonResponse
    {
        return ApiResponse::data([
            'user_id' => $user->getKey(),
            'roles' => $user->fresh('roles')->roles->pluck('name')->values()->all(),
        ]);
    }
}
