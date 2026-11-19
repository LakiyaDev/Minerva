<?php

declare(strict_types=1);

namespace App\Modules\Identity\Policies;

use App\Modules\Identity\Models\User;

final class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('user.manage');
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->hasPermission('user.manage');
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->hasPermission('user.manage');
    }

    public function manageRoles(User $actor, User $target): bool
    {
        return $actor->hasPermission('user.manage') && ! $actor->is($target);
    }

    public function managePublisher(User $actor, User $target): bool
    {
        return $actor->hasPermission('publisher.manage') && ! $actor->is($target);
    }
}
