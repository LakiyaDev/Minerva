<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Api\V1\Auth;

use App\Modules\Identity\Exceptions\IdentityException;
use App\Modules\Identity\Http\Requests\VerifyMfaRequest;
use App\Modules\Identity\Http\Resources\MeResource;
use App\Modules\Identity\Services\LoginChallengeService;
use App\Modules\Identity\Services\SessionService;
use App\Modules\Identity\Services\TwoFactorService;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

final class StaffMfaVerifyController
{
    public function __invoke(
        VerifyMfaRequest $request,
        LoginChallengeService $challenges,
        TwoFactorService $twoFactor,
        SessionService $sessions,
    ): JsonResponse {
        $token = (string) $request->string('token');

        $user = $challenges->resolve($token, LoginChallengeService::PURPOSE_MFA_CHALLENGE);

        if ($user === null) {
            throw IdentityException::invalidMfaChallenge();
        }

        if (! $twoFactor->verifyForLogin($user, (string) $request->string('code'), $request)) {
            if (! $challenges->registerFailure($token)) {
                throw IdentityException::invalidMfaChallenge();
            }

            throw IdentityException::invalidMfaCode();
        }

        $challenges->consume($token);

        $sessions->login($user, $request, 'staff_mfa');

        return ApiResponse::data([
            'status' => 'authenticated',
            'user' => new MeResource($user->load('roles')),
        ]);
    }
}
