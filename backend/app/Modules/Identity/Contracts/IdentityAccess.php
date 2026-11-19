<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Public contract other modules use to check identity eligibility without
 * reaching into Identity models or tables.
 */
interface IdentityAccess
{
    public function isActive(Authenticatable $user): bool;

    public function hasPermission(Authenticatable $user, string $permission): bool;

    public function hasAnyPermission(Authenticatable $user, array $permissions): bool;

    public function isParticipant(Authenticatable $user): bool;

    public function isPublisher(Authenticatable $user): bool;

    public function canPublishDirectly(Authenticatable $user): bool;
}
