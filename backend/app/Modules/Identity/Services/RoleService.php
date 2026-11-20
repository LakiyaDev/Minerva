<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Events\RoleAssigned;
use App\Modules\Identity\Events\RoleRevoked;
use App\Modules\Identity\Exceptions\IdentityException;
use App\Modules\Identity\Models\PublisherPermission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Shared\Contracts\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class RoleService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function assign(User $target, string $roleName, User $actor, Request $request): Role
    {
        $this->guard($target, $actor);

        $role = Role::query()->where('name', $roleName)->first();

        if ($role === null || ! in_array($roleName, config('identity.staff.assignable_roles', []), true)) {
            throw IdentityException::roleNotAssignable($roleName);
        }

        // Staff roles unlock staff endpoints and must therefore be paired with
        // a password credential so TOTP cannot be bypassed by social login.
        if (
            in_array($roleName, config('identity.staff.roles', []), true)
            && ! $target->hasPasswordCredential()
        ) {
            throw IdentityException::conflict(
                'Staff roles require a password credential. Invite the user as a staff member instead.'
            );
        }

        // Only an administrator may mint another administrator.
        if ($roleName === 'administrator' && ! $actor->hasRole('administrator')) {
            throw IdentityException::roleNotAssignable($roleName);
        }

        DB::transaction(function () use ($target, $role, $actor): void {
            $target->roles()->syncWithoutDetaching([
                $role->getKey() => ['assigned_by' => $actor->getKey()],
            ]);
        });

        $target->flushPermissionCache();

        RoleAssigned::dispatch($target, $roleName, $actor);

        $this->audit->record('identity.role.assigned', $actor, $target, [
            'role' => $roleName,
        ], $request);

        return $role;
    }

    public function revoke(User $target, string $roleName, User $actor, Request $request): void
    {
        $this->guard($target, $actor);

        if (
            $roleName === 'administrator'
            && (bool) config('identity.staff.protect_last_administrator', true)
            && $this->isLastActiveAdministrator($target)
        ) {
            throw IdentityException::lastAdministrator();
        }

        $role = Role::query()->where('name', $roleName)->first();

        if ($role === null) {
            throw IdentityException::roleNotAssignable($roleName);
        }

        DB::transaction(function () use ($target, $role, $roleName): void {
            $target->roles()->detach($role->getKey());

            // Removing the publisher role must also retire any active
            // publisher grant; otherwise re-assigning the role would silently
            // restore `article.publish_own` without a fresh authorization.
            if ($roleName === 'publisher') {
                PublisherPermission::query()
                    ->where('user_id', $target->getKey())
                    ->whereNull('revoked_at')
                    ->update(['revoked_at' => now()]);
            }
        });

        $target->flushPermissionCache();

        RoleRevoked::dispatch($target, $roleName, $actor);

        $this->audit->record('identity.role.revoked', $actor, $target, [
            'role' => $roleName,
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

    private function isLastActiveAdministrator(User $target): bool
    {
        if (! $target->hasRole('administrator') || ! $target->isActive()) {
            return false;
        }

        $activeAdministrators = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->where('name', 'administrator'))
            ->count();

        return $activeAdministrators <= 1;
    }
}
