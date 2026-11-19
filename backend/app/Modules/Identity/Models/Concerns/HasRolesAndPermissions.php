<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models\Concerns;

use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Services\PermissionResolver;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasRolesAndPermissions
{
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')
            ->withPivot('assigned_by')
            ->withTimestamps();
    }

    /**
     * Direct, time-bound permission grants that are independent of roles.
     */
    public function directPermissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_permissions')
            ->withPivot(['granted_by', 'expires_at'])
            ->withTimestamps();
    }

    /**
     * Effective permission names (roles + non-expired direct grants + active
     * publisher direct-publish capability).
     *
     * @return array<int, string>
     */
    public function effectivePermissions(): array
    {
        return app(PermissionResolver::class)->for($this);
    }

    public function hasRole(string $role): bool
    {
        return $this->roles->contains(fn (Role $candidate): bool => $candidate->name === $role);
    }

    /**
     * @param  array<int, string>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return $this->roles->contains(fn (Role $candidate): bool => in_array($candidate->name, $roles, true));
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->effectivePermissions(), true);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        return array_intersect($permissions, $this->effectivePermissions()) !== [];
    }

    public function flushPermissionCache(): void
    {
        app(PermissionResolver::class)->forget($this);
    }
}
