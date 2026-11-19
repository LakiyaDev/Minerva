<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Api\V1\Admin;

use App\Modules\Identity\Http\Requests\UpdateUserRequest;
use App\Modules\Identity\Http\Resources\AdminUserResource;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\UserManagementService;
use App\Shared\Contracts\AuditLogger;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class AdminUserController
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);

        $query = User::query()->with(['roles', 'publisherGrants', 'directPermissions']);

        if (($search = trim((string) $request->query('q', ''))) !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('email', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%');
            });
        }

        if (($role = (string) $request->query('role', '')) !== '') {
            $query->whereHas('roles', fn ($builder) => $builder->where('name', $role));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $users = $query->orderByDesc('created_at')->paginate($perPage)->withQueryString();

        return ApiResponse::data(AdminUserResource::collection($users));
    }

    public function show(User $user): AdminUserResource
    {
        Gate::authorize('view', $user);

        return AdminUserResource::make(
            $user->load(['roles', 'publisherGrants', 'directPermissions'])
        );
    }

    public function update(
        UpdateUserRequest $request,
        User $user,
        UserManagementService $users,
        AuditLogger $audit,
    ): AdminUserResource {
        Gate::authorize('update', $user);

        /** @var User $actor */
        $actor = $request->user();

        if ($request->filled('name')) {
            $user->forceFill(['name' => trim((string) $request->string('name'))])->save();

            $audit->record('identity.user.updated', $actor, $user, [
                'fields' => ['name'],
            ], $request);
        }

        if ($request->has('is_active')) {
            $users->setActive($user, $request->boolean('is_active'), $actor, $request);
        }

        return AdminUserResource::make(
            $user->fresh(['roles', 'publisherGrants', 'directPermissions'])
        );
    }
}
