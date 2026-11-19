<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Api\V1\Auth;

use App\Modules\Identity\Services\OAuthStateService;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Laravel\Socialite\Facades\Socialite;

final class GoogleRedirectController
{
    public function __invoke(OAuthStateService $states): JsonResponse
    {
        $state = $states->issue();

        $authorizationUrl = Socialite::driver('google')
            ->stateless()
            ->scopes(config('identity.google.scopes', ['openid', 'profile', 'email']))
            ->with([
                'state' => $state,
                'access_type' => 'offline',
                'prompt' => 'select_account',
                'include_granted_scopes' => 'true',
            ])
            ->redirect()
            ->getTargetUrl();

        return ApiResponse::data([
            'authorization_url' => $authorizationUrl,
            'expires_in' => (int) config('identity.google.state_ttl_seconds', 600),
        ]);
    }
}
