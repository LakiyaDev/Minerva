<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Api\V1\Auth;

use App\Modules\Identity\Exceptions\IdentityException;
use App\Modules\Identity\Http\Requests\ConfirmMfaRequest;
use App\Modules\Identity\Http\Requests\SetupMfaRequest;
use App\Modules\Identity\Http\Resources\MeResource;
use App\Modules\Identity\Services\LoginChallengeService;
use App\Modules\Identity\Services\SessionService;
use App\Modules\Identity\Services\TwoFactorService;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

final class StaffMfaSetupController
{
    public function store(
        SetupMfaRequest $request,
        LoginChallengeService $challenges,
        TwoFactorService $twoFactor,
    ): JsonResponse {
        $user = $challenges->resolve(
            (string) $request->string('token'),
            LoginChallengeService::PURPOSE_MFA_SETUP,
        );

        if ($user === null) {
            throw IdentityException::invalidMfaChallenge();
        }

        return ApiResponse::data($twoFactor->beginSetup($user, $request));
    }

    public function confirm(
        ConfirmMfaRequest $request,
        LoginChallengeService $challenges,
        TwoFactorService $twoFactor,
        SessionService $sessions,
    ): JsonResponse {
        $token = (string) $request->string('token');

        $user = $challenges->resolve($token, LoginChallengeService::PURPOSE_MFA_SETUP);

        if ($user === null) {
            throw IdentityException::invalidMfaChallenge();
        }

        $recoveryCodes = $twoFactor->confirmSetup($user, (string) $request->string('code'), $request);

        $challenges->consume($token);

        $sessions->login($user, $request, 'staff_mfa_setup');

        return ApiResponse::created([
            'status' => 'authenticated',
            'recovery_codes' => $recoveryCodes,
            'user' => new MeResource($user->load('roles')),
        ]);
    }
}
