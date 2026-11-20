<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Api\V1\Auth;

use App\Modules\Identity\Exceptions\IdentityException;
use App\Modules\Identity\Services\GoogleAuthenticationService;
use App\Modules\Identity\Services\OAuthStateService;
use App\Modules\Identity\Services\SessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

final class GoogleCallbackController
{
    public function __invoke(
        Request $request,
        OAuthStateService $states,
        GoogleAuthenticationService $google,
        SessionService $sessions,
    ): RedirectResponse {
        $state = (string) $request->query('state', '');

        if (! $states->consume($state)) {
            return $this->failure('invalid_state');
        }

        $error = $request->query('error');

        if (is_string($error) && $error !== '') {
            return $this->failure($error === 'access_denied' ? 'access_denied' : 'google_error');
        }

        if (! $request->filled('code')) {
            return $this->failure('missing_code');
        }

        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            $user = $google->authenticate($googleUser, $request);

            $sessions->login($user, $request, 'google');

            return $this->success();
        } catch (IdentityException $exception) {
            return $this->failure($exception->errorCode());
        } catch (Throwable $exception) {
            Log::error('Google OAuth callback failed', [
                'request_id' => $request->attributes->get('request_id'),
                'exception' => $exception,
            ]);

            return $this->failure('oauth_failed');
        }
    }

    private function success(): RedirectResponse
    {
        return redirect()->away($this->urlWith([
            (string) config('identity.google.redirect_status_param', 'auth') => 'success',
        ], (string) config('identity.google.success_redirect')));
    }

    private function failure(string $reason): RedirectResponse
    {
        return redirect()->away($this->urlWith([
            (string) config('identity.google.redirect_status_param', 'auth') => 'error',
            (string) config('identity.google.redirect_reason_param', 'reason') => $reason,
        ], (string) config('identity.google.failure_redirect')));
    }

    /**
     * @param  array<string, string>  $params
     */
    private function urlWith(array $params, string $base): string
    {
        $separator = str_contains($base, '?') ? '&' : '?';

        return $base.$separator.http_build_query($params);
    }
}
