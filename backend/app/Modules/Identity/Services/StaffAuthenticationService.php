<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Exceptions\IdentityException;
use App\Modules\Identity\Models\LoginAttempt;
use App\Modules\Identity\Models\User;
use App\Shared\Contracts\AuditLogger;
use App\Shared\Support\EmailNormalizer;
use App\Shared\Support\IpHasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class StaffAuthenticationService
{
    /**
     * A valid bcrypt hash used only to equalise response time for unknown
     * accounts (prevents account enumeration through timing).
     */
    private const DUMMY_HASH = '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly LoginChallengeService $challenges,
    ) {}

    /**
     * @return array{status: string, user: User, token?: string}
     */
    public function attempt(string $email, string $password, Request $request): array
    {
        $email = EmailNormalizer::normalize($email);
        $user = User::query()->where('email', $email)->first();

        if ($user !== null && $user->locked_until !== null && $user->locked_until->isFuture()) {
            $this->recordAttempt($email, $user, $request, false);

            throw IdentityException::accountLocked($user->locked_until);
        }

        if ($user === null || ! $user->hasPasswordCredential()) {
            // Equalise timing for unknown accounts and Google-only readers.
            Hash::check($password, self::DUMMY_HASH);
            $valid = false;
        } else {
            $valid = Hash::check($password, (string) $user->password);
        }

        if (! $valid) {
            if ($user !== null && $user->hasPasswordCredential()) {
                $this->recordFailure($user, $request);
            }

            $this->recordAttempt($email, $user, $request, false);

            $this->audit->record('auth.staff.login_failed', $user, null, [
                'email_hash' => hash_hmac('sha256', $email, (string) config('app.key')),
            ], $request);

            throw IdentityException::invalidCredentials();
        }

        if (! $user->isActive()) {
            $this->recordAttempt($email, $user, $request, false);

            throw IdentityException::accountInactive();
        }

        if (! $user->isStaff()) {
            $this->recordAttempt($email, $user, $request, false);

            throw IdentityException::staffRoleRequired();
        }

        $user->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ])->save();

        $this->recordAttempt($email, $user, $request, true);

        if (! (bool) config('identity.staff.mfa_required', true)) {
            return ['status' => 'authenticated', 'user' => $user];
        }

        if (! $user->hasConfirmedTwoFactor()) {
            return [
                'status' => 'mfa_setup_required',
                'user' => $user,
                'token' => $this->challenges->issue($user, LoginChallengeService::PURPOSE_MFA_SETUP),
            ];
        }

        return [
            'status' => 'mfa_required',
            'user' => $user,
            'token' => $this->challenges->issue($user, LoginChallengeService::PURPOSE_MFA_CHALLENGE),
        ];
    }

    private function recordFailure(User $user, Request $request): void
    {
        $attempts = $user->failed_login_attempts + 1;
        $threshold = (int) config('identity.staff.lockout_threshold', 5);

        $user->forceFill([
            'failed_login_attempts' => $attempts,
            'locked_until' => $attempts >= $threshold
                ? now()->addMinutes((int) config('identity.staff.lockout_minutes', 15))
                : $user->locked_until,
        ])->save();

        if ($attempts >= $threshold) {
            $this->audit->record('auth.staff.locked', $user, $user, [
                'attempts' => $attempts,
            ], $request);
        }
    }

    private function recordAttempt(string $email, ?User $user, Request $request, bool $successful): void
    {
        LoginAttempt::query()->create([
            'email' => $email,
            'user_id' => $user?->getKey(),
            'ip_hash' => IpHasher::hash(null, $request),
            'successful' => $successful,
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'attempted_at' => now(),
        ]);
    }
}
