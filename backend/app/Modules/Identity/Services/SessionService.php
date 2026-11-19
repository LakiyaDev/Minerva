<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Events\UserSignedIn;
use App\Modules\Identity\Exceptions\IdentityException;
use App\Modules\Identity\Models\User;
use App\Shared\Contracts\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

final class SessionService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function login(User $user, Request $request, string $channel): void
    {
        if (! $user->isActive()) {
            throw IdentityException::accountInactive();
        }

        Auth::guard('web')->login($user);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $user->forceFill([
            'last_login_at' => now(),
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ])->save();

        UserSignedIn::dispatch($user, $channel);

        $this->audit->record('auth.signed_in', $user, $user, ['channel' => $channel], $request);
    }

    public function logout(Request $request): void
    {
        $user = $request->user();

        if ($user instanceof User) {
            $this->audit->record('auth.signed_out', $user, $user, [], $request);

            $token = $user->currentAccessToken();

            if ($token instanceof PersonalAccessToken) {
                $token->delete();
            }
        }

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }

    /**
     * Revoke all sessions and personal access tokens except the current one.
     */
    public function revokeOtherSessions(User $user, Request $request): int
    {
        $currentSessionId = $request->hasSession() ? $request->session()->getId() : null;

        $revokedSessions = 0;

        if (config('session.driver') === 'database') {
            $query = DB::table((string) config('session.table', 'sessions'))
                ->where('user_id', $user->getKey());

            if ($currentSessionId !== null) {
                $query->where('id', '!=', $currentSessionId);
            }

            $revokedSessions = $query->delete();
        }

        $currentToken = $user->currentAccessToken();
        $currentTokenId = $currentToken instanceof PersonalAccessToken ? $currentToken->getKey() : null;

        $tokens = $user->tokens();

        if ($currentTokenId !== null) {
            $tokens->whereKeyNot($currentTokenId);
        }

        $tokens->delete();

        $this->audit->record('auth.sessions_revoked', $user, $user, [
            'sessions' => $revokedSessions,
        ], $request);

        return $revokedSessions;
    }

    public function revokeAllSessions(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::table((string) config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->delete();
        }

        $user->tokens()->delete();
    }
}
