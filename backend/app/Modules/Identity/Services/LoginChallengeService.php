<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\User;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Str;

/**
 * Short-lived, single-use tokens issued *before* a session exists:
 *   - mfa_challenge: correct password, TOTP verification pending
 *   - mfa_setup:     correct password, staff has not enrolled TOTP yet
 *
 * Only the SHA-256 hash of the token is used as the cache key, so a cache dump
 * does not expose usable credentials.
 */
final class LoginChallengeService
{
    public const PURPOSE_MFA_CHALLENGE = 'mfa_challenge';

    public const PURPOSE_MFA_SETUP = 'mfa_setup';

    public function __construct(private readonly Cache $cache) {}

    public function issue(User $user, string $purpose): string
    {
        $token = Str::random(64);
        $ttl = (int) config('identity.staff.challenge_ttl_seconds', 300);

        $this->cache->put($this->key($token), [
            'user_id' => $user->getKey(),
            'purpose' => $purpose,
            'attempts' => 0,
            'expires_at' => now()->addSeconds($ttl)->getTimestamp(),
        ], now()->addSeconds($ttl));

        return $token;
    }

    public function resolve(string $token, string $purpose): ?User
    {
        $payload = $this->cache->get($this->key($token));

        if (! is_array($payload) || ($payload['purpose'] ?? null) !== $purpose) {
            return null;
        }

        $expiresAt = (int) ($payload['expires_at'] ?? 0);

        if ($expiresAt > 0 && $expiresAt < now()->getTimestamp()) {
            return null;
        }

        $user = User::query()->find($payload['user_id'] ?? null);

        if ($user === null || ! $user->isActive() || ! $user->isStaff()) {
            return null;
        }

        return $user;
    }

    /**
     * Count a failed verification attempt. Returns false once the challenge is
     * exhausted (and therefore burnt).
     */
    public function registerFailure(string $token): bool
    {
        $key = $this->key($token);
        $payload = $this->cache->get($key);

        if (! is_array($payload)) {
            return false;
        }

        $attempts = (int) ($payload['attempts'] ?? 0) + 1;
        $max = (int) config('identity.staff.challenge_max_attempts', 5);

        $remaining = (int) ($payload['expires_at'] ?? 0) - now()->getTimestamp();

        if ($remaining <= 0 || $attempts >= $max) {
            $this->cache->forget($key);

            return false;
        }

        $payload['attempts'] = $attempts;

        // Do not extend the challenge lifetime on failure: reuse the time left
        // on the original issue window.
        $this->cache->put($key, $payload, now()->addSeconds($remaining));

        return true;
    }

    public function consume(string $token): void
    {
        $this->cache->forget($this->key($token));
    }

    private function key(string $token): string
    {
        return 'identity:login-challenge:'.hash('sha256', $token);
    }
}
