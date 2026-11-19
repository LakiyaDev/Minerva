<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\User;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\DB;

final class PermissionResolver
{
    public function __construct(private readonly Cache $cache) {}

    /**
     * Resolve the effective permission names for a user:
     *   role permissions
     * + non-expired direct grants
     * + "article.publish_own" while an active publisher grant allows it.
     *
     * @return array<int, string>
     */
    public function for(User $user): array
    {
        return $this->cache->remember(
            $this->cacheKey($user),
            now()->addSeconds((int) config('identity.cache.permission_ttl_seconds', 300)),
            function () use ($user): array {
                $rolePermissions = DB::table('permissions')
                    ->join('permission_role', 'permissions.id', '=', 'permission_role.permission_id')
                    ->join('role_user', 'permission_role.role_id', '=', 'role_user.role_id')
                    ->where('role_user.user_id', $user->getKey())
                    ->pluck('permissions.name')
                    ->all();

                $directPermissions = DB::table('permissions')
                    ->join('user_permissions', 'permissions.id', '=', 'user_permissions.permission_id')
                    ->where('user_permissions.user_id', $user->getKey())
                    ->where(function ($query): void {
                        $query->whereNull('user_permissions.expires_at')
                            ->orWhere('user_permissions.expires_at', '>', now());
                    })
                    ->pluck('permissions.name')
                    ->all();

                $permissions = array_values(array_unique([...$rolePermissions, ...$directPermissions]));

                if ($this->canPublishDirectly($user)) {
                    $permissions[] = (string) config('identity.publisher.direct_publish_permission');
                }

                $permissions = array_values(array_unique($permissions));

                sort($permissions);

                return $permissions;
            }
        );
    }

    public function canPublishDirectly(User $user): bool
    {
        return DB::table('publisher_permissions')
            ->where('user_id', $user->getKey())
            ->whereNull('revoked_at')
            ->where('can_publish_directly', true)
            ->exists();
    }

    public function forget(User $user): void
    {
        $this->cache->forget($this->cacheKey($user));
    }

    private function cacheKey(User $user): string
    {
        return sprintf('%s:permissions:%s', config('identity.cache.prefix', 'identity'), $user->getKey());
    }
}
