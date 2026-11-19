<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Events\PublisherPermissionChanged;
use App\Modules\Identity\Exceptions\IdentityException;
use App\Modules\Identity\Models\PublisherPermission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Shared\Contracts\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class PublisherPermissionService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function grant(
        User $target,
        bool $canPublishDirectly,
        User $actor,
        Request $request,
    ): PublisherPermission {
        $this->guard($target, $actor);

        if (! $target->hasPasswordCredential()) {
            throw IdentityException::conflict(
                'Publisher permission requires a staff password credential. Invite the user as a staff member first.'
            );
        }

        $grant = DB::transaction(function () use ($target, $canPublishDirectly, $actor): PublisherPermission {
            // At most one active grant; revoke any previous one first.
            PublisherPermission::query()
                ->where('user_id', $target->getKey())
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->update(['revoked_at' => now()]);

            $grant = PublisherPermission::query()->create([
                'user_id' => $target->getKey(),
                'can_publish_directly' => $canPublishDirectly,
                'granted_by' => $actor->getKey(),
                'granted_at' => now(),
            ]);

            $role = Role::query()->where('name', 'publisher')->firstOrFail();

            $target->roles()->syncWithoutDetaching([
                $role->getKey() => ['assigned_by' => $actor->getKey()],
            ]);

            return $grant;
        });

        $target->flushPermissionCache();

        PublisherPermissionChanged::dispatch($target, 'granted', $grant, $actor);

        $this->audit->record('identity.publisher.granted', $actor, $target, [
            'can_publish_directly' => $canPublishDirectly,
        ], $request);

        return $grant;
    }

    public function updateDirectPublish(
        User $target,
        bool $canPublishDirectly,
        User $actor,
        Request $request,
    ): PublisherPermission {
        $this->guard($target, $actor);

        $grant = PublisherPermission::query()
            ->where('user_id', $target->getKey())
            ->whereNull('revoked_at')
            ->first();

        if ($grant === null) {
            throw IdentityException::conflict('This user has no active publisher grant.');
        }

        $grant->forceFill(['can_publish_directly' => $canPublishDirectly])->save();

        $target->flushPermissionCache();

        PublisherPermissionChanged::dispatch($target, 'updated', $grant, $actor);

        $this->audit->record('identity.publisher.updated', $actor, $target, [
            'can_publish_directly' => $canPublishDirectly,
        ], $request);

        return $grant;
    }

    public function revoke(User $target, User $actor, Request $request): void
    {
        $this->guard($target, $actor);

        DB::transaction(function () use ($target): void {
            PublisherPermission::query()
                ->where('user_id', $target->getKey())
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->update(['revoked_at' => now()]);

            $role = Role::query()->where('name', 'publisher')->first();

            if ($role !== null) {
                $target->roles()->detach($role->getKey());
            }
        });

        $target->flushPermissionCache();

        PublisherPermissionChanged::dispatch($target, 'revoked', null, $actor);

        $this->audit->record('identity.publisher.revoked', $actor, $target, [], $request);
    }

    private function guard(User $target, User $actor): void
    {
        if ($actor->is($target)) {
            throw IdentityException::selfRoleChange();
        }

        if (! $actor->hasPermission('publisher.manage')) {
            throw new AuthorizationException('You do not have the required permission.');
        }
    }
}
