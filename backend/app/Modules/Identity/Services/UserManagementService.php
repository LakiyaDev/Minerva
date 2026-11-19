<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Events\UserDeactivated;
use App\Modules\Identity\Exceptions\IdentityException;
use App\Modules\Identity\Models\User;
use App\Shared\Contracts\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UserManagementService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SessionService $sessions,
    ) {}

    public function setActive(User $target, bool $active, User $actor, Request $request): User
    {
        if (! $active && $actor->is($target)) {
            throw IdentityException::conflict('You cannot deactivate your own account.');
        }

        if (! $active) {
            $this->guardLastAdministrator($target);
        }

        $target->forceFill(['is_active' => $active])->save();

        if (! $active) {
            $this->sessions->revokeAllSessions($target);

            UserDeactivated::dispatch($target);
        }

        $this->audit->record(
            $active ? 'identity.user.activated' : 'identity.user.deactivated',
            $actor,
            $target,
            [],
            $request,
        );

        return $target;
    }

    private function guardLastAdministrator(User $target): void
    {
        if (! $target->hasRole('administrator') || ! $target->isActive()) {
            return;
        }

        $activeAdministrators = User::query()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereHas('roles', fn ($query) => $query->where('name', 'administrator'))
            ->count();

        if ($activeAdministrators <= 1) {
            throw IdentityException::lastAdministrator();
        }
    }
}
