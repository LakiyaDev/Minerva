<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Api\V1\Admin;

use App\Modules\Identity\Http\Resources\RoleResource;
use App\Modules\Identity\Models\Role;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

final class AdminRoleController
{
    public function __invoke(): JsonResponse
    {
        $roles = Role::query()->with('permissions')->orderBy('name')->get();

        return ApiResponse::data(RoleResource::collection($roles));
    }
}
