<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Events\PermissionGranted;
use App\Modules\Identity\Events\PermissionRevoked;
use App\Modules\Identity\Exceptions\IdentityException;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserPermission;
use App\Shared\Contracts\AuditLogger;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class PermissionGrantService
{
    /**
     * Sensitive permissions must be obtained through a role, never as a direct
     * grant — this limits the blast radius of a compromised admin workflow.
     */
    private const DIRECT_GRANT_BLOCKLIST = [
        'user.manage',
        'publisher.manage',
        'settings.manage',
        'audit.view',
        'article.publish_any',
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    public function grant(
        User $target,
        string $permissionName,
        User $actor,
        ?CarbonInterface $expiresAt,
        Request $request,
    ): UserPermission {
        $this->guard($target, $actor);

        if (in_array($permissionName, self::DIRECT_GRANT_BLOCKLIST, true)) {
            throw IdentityException::conflict('This permission can only be granted through a role.');
        }

        $permission = Permission::query()->where('name', $permissionName)->first();

        if ($permission === null) {
            throw IdentityException::roleNotAssignable($permissionName);
        }

        $grant = DB::transaction(fn (): UserPermission => UserPermission::query()->updateOrCreate(
            [
                'user_id' => $target->getKey(),
                'permission_id' => $permission->getKey(),
            ],
            [
                'granted_by' => $actor->getKey(),
                'expires_at' => $expiresAt,
            ],
        ));

        $target->flushPermissionCache();

        PermissionGranted::dispatch(
            $target,
            $permissionName,
            $actor,
            $expiresAt?->toIso8601String(),
        );

        $this->audit->record('identity.permission.granted', $actor, $target, [
            'permission' => $permissionName,
            'expires_at' => $expiresAt?->toIso8601String(),
        ], $request);

        return $grant;
    }

    public function revoke(User $target, string $permissionName, User $actor, Request $request): void
    {
        $this->guard($target, $actor);

        $permission = Permission::query()->where('name', $permissionName)->first();

        if ($permission === null) {
            throw IdentityException::roleNotAssignable($permissionName);
        }

        DB::transaction(function () use ($target, $permission): void {
            UserPermission::query()
                ->where('user_id', $target->getKey())
                ->where('permission_id', $permission->getKey())
                ->delete();
        });

        $target->flushPermissionCache();

        PermissionRevoked::dispatch($target, $permissionName, $actor);

        $this->audit->record('identity.permission.revoked', $actor, $target, [
            'permission' => $permissionName,
        ], $request);
    }

    private function guard(User $target, User $actor): void
    {
        if ($actor->is($target)) {
            throw IdentityException::selfRoleChange();
        }

        if (! $actor->hasPermission('user.manage')) {
            throw new AuthorizationException('You do not have the required permission.');
        }
    }
}
