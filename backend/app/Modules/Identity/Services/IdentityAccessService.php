<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Contracts\IdentityAccess;
use App\Modules\Identity\Models\PublisherPermission;
use App\Modules\Identity\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

final class IdentityAccessService implements IdentityAccess
{
    public function __construct(private readonly PermissionResolver $permissions) {}

    public function isActive(Authenticatable $user): bool
    {
        return $user instanceof User && $user->isActive();
    }

    public function hasPermission(Authenticatable $user, string $permission): bool
    {
        return $user instanceof User && $user->hasPermission($permission);
    }

    public function hasAnyPermission(Authenticatable $user, array $permissions): bool
    {
        return $user instanceof User && $user->hasAnyPermission($permissions);
    }

    public function isParticipant(Authenticatable $user): bool
    {
        return $user instanceof User && $user->isParticipant();
    }

    public function isPublisher(Authenticatable $user): bool
    {
        return $user instanceof User
            && $user->hasRole('publisher')
            && PublisherPermission::query()->active()->where('user_id', $user->getKey())->exists();
    }

    public function canPublishDirectly(Authenticatable $user): bool
    {
        return $user instanceof User && $this->permissions->canPublishDirectly($user);
    }
}
