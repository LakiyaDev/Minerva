<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Events\StaffInvited;
use App\Modules\Identity\Events\UserRegistered;
use App\Modules\Identity\Exceptions\IdentityException;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\StaffInvitation;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Notifications\StaffInvitationNotification;
use App\Shared\Contracts\AuditLogger;
use App\Shared\Support\EmailNormalizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

final class StaffInvitationService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function invite(
        User $actor,
        string $email,
        string $role,
        Request $request,
    ): StaffInvitation {
        if (! $actor->hasPermission('user.manage')) {
            throw new AuthorizationException('You do not have the required permission.');
        }

        $email = EmailNormalizer::normalize($email);

        if (! in_array($role, config('identity.staff.roles', []), true)) {
            throw IdentityException::roleNotAssignable($role);
        }

        $existing = User::withTrashed()->where('email', $email)->first();

        // A reader account may be upgraded to staff (keeping the participant
        // role); a password-bearing or staff account must not be re-invited.
        if (
            $existing !== null
            && ($existing->trashed() || $existing->hasPasswordCredential() || $existing->isStaff())
        ) {
            throw IdentityException::conflict('An account already exists for this email address.');
        }

        // Retire any earlier pending invitation for the same address.
        StaffInvitation::query()
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->update(['expires_at' => now()]);

        $token = Str::random(64);

        $invitation = DB::transaction(fn (): StaffInvitation => StaffInvitation::query()->create([
            'email' => $email,
            'role' => $role,
            'invited_by' => $actor->getKey(),
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addHours((int) config('identity.staff.invitation_ttl_hours', 72)),
        ]));

        Notification::route('mail', $email)->notify(
            new StaffInvitationNotification($token, $role, $actor->name)
        );

        StaffInvited::dispatch($invitation, $actor);

        $this->audit->record('auth.staff.invited', $actor, $invitation, [
            'role' => $role,
            // Email is retained in the invitation row; the audit keeps a hash.
            'email_hash' => hash_hmac('sha256', $email, (string) config('app.key')),
        ], $request);

        return $invitation;
    }

    public function accept(
        string $token,
        string $name,
        string $password,
        Request $request,
    ): User {
        $invitation = StaffInvitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if ($invitation === null || ! $invitation->isUsable()) {
            throw IdentityException::invalidInvitation();
        }

        $user = DB::transaction(function () use ($invitation, $name, $password): User {
            $locked = StaffInvitation::query()->lockForUpdate()->find($invitation->getKey());

            if ($locked === null || ! $locked->isUsable()) {
                throw IdentityException::invalidInvitation();
            }

            $existing = User::withTrashed()
                ->where('email', $locked->email)
                ->lockForUpdate()
                ->first();

            if (
                $existing !== null
                && ($existing->trashed() || $existing->hasPasswordCredential() || $existing->isStaff())
            ) {
                throw IdentityException::conflict('An account already exists for this email address.');
            }

            $created = $existing === null;

            if ($created) {
                $user = User::query()->create([
                    'name' => $name,
                    'email' => $locked->email,
                    'password' => $password,
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]);
            } else {
                // Upgrade a Google-only reader: keep the account (and its
                // participant role) but add the staff credential.
                $existing->forceFill([
                    'name' => $name,
                    'password' => $password,
                    'email_verified_at' => $existing->email_verified_at ?? now(),
                    'is_active' => true,
                    'failed_login_attempts' => 0,
                    'locked_until' => null,
                    'remember_token' => Str::random(60),
                ])->save();

                $user = $existing;
            }

            $role = Role::query()->where('name', $locked->role)->firstOrFail();

            $user->roles()->syncWithoutDetaching([
                $role->getKey() => ['assigned_by' => $locked->invited_by],
            ]);

            $locked->forceFill(['accepted_at' => now()])->save();

            if ($created) {
                UserRegistered::dispatch($user);
            }

            return $user;
        });

        $user->flushPermissionCache();

        $this->audit->record('auth.staff.invitation_accepted', $user, $user, [
            'role' => $invitation->role,
        ], $request);

        return $user;
    }
}
