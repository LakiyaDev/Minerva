<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Events\UserRegistered;
use App\Modules\Identity\Exceptions\IdentityException;
use App\Modules\Identity\Models\OAuthAccount;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Shared\Contracts\AuditLogger;
use App\Shared\Support\EmailNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Contracts\User as GoogleUser;
use RuntimeException;

final class GoogleAuthenticationService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Authenticate or provision a reader from a verified Google identity.
     * Staff accounts are deliberately excluded from this flow: they must use
     * password + TOTP so that social login can never bypass MFA.
     */
    public function authenticate(GoogleUser $googleUser, Request $request): User
    {
        $providerId = (string) $googleUser->getId();
        $email = EmailNormalizer::normalize($googleUser->getEmail());
        $emailVerified = filter_var(
            $googleUser->user['email_verified'] ?? $googleUser->user['verified_email'] ?? false,
            FILTER_VALIDATE_BOOL,
        );

        if ($providerId === '' || $email === '' || ! $emailVerified) {
            throw IdentityException::googleEmailUnverified();
        }

        $user = DB::transaction(function () use ($providerId, $email, $googleUser): User {
            $account = OAuthAccount::query()
                ->where('provider', 'google')
                ->where('provider_user_id', $providerId)
                ->lockForUpdate()
                ->first();

            if ($account !== null) {
                $account->forceFill([
                    'email' => $email,
                    'avatar_url' => $googleUser->getAvatar(),
                ])->save();

                $user = $account->user()->withTrashed()->first();

                if ($user === null || $user->trashed()) {
                    throw IdentityException::accountInactive();
                }

                // A reader can be upgraded to staff (invitation flow) while
                // keeping the linked Google identity. That link must not become
                // an MFA bypass, so the staff check is repeated here.
                if ($user->hasPasswordCredential() || $user->isStaff()) {
                    throw IdentityException::googleStaffAccount();
                }

                return $user;
            }

            $user = User::withTrashed()->where('email', $email)->lockForUpdate()->first();

            if ($user !== null && $user->trashed()) {
                throw IdentityException::accountInactive();
            }

            if ($user !== null && ($user->hasPasswordCredential() || $user->isStaff())) {
                // Never let Google sign-in bypass staff MFA. A staff role must
                // be paired with a password credential (and therefore TOTP).
                throw IdentityException::googleStaffAccount();
            }

            if ($user === null) {
                $user = User::query()->create([
                    'name' => $googleUser->getName() ?: $email,
                    'email' => $email,
                    'password' => null,
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]);

                UserRegistered::dispatch($user);
            }

            if (! $user->hasRole('participant')) {
                $this->assignReaderRole($user);
            }

            OAuthAccount::query()->create([
                'user_id' => $user->getKey(),
                'provider' => 'google',
                'provider_user_id' => $providerId,
                'email' => $email,
                'avatar_url' => $googleUser->getAvatar(),
            ]);

            return $user;
        });

        if (! $user->isActive()) {
            throw IdentityException::accountInactive();
        }

        $this->audit->record('auth.google.signed_in', $user, $user, [
            'provider' => 'google',
        ], $request);

        return $user;
    }

    private function assignReaderRole(User $user): void
    {
        $role = Role::query()->where('name', 'participant')->first();

        if ($role === null) {
            throw new RuntimeException('Identity roles are not seeded. Run: php artisan db:seed --class=RolePermissionSeeder');
        }

        $user->roles()->syncWithoutDetaching([$role->getKey()]);

        // The relation may already be loaded (isStaff() reads it), so drop the
        // stale copy and reload the freshly assigned roles.
        $user->unsetRelation('roles');
        $user->load('roles');
        $user->flushPermissionCache();
    }
}
