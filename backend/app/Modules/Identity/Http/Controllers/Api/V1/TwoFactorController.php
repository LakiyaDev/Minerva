<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Api\V1;

use App\Modules\Identity\Http\Requests\ConfirmTwoFactorRequest;
use App\Modules\Identity\Http\Requests\DisableTwoFactorRequest;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\TwoFactorService;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TwoFactorController
{
    public function setup(Request $request, TwoFactorService $twoFactor): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::data($twoFactor->beginSetup($user, $request));
    }

    public function confirm(
        ConfirmTwoFactorRequest $request,
        TwoFactorService $twoFactor,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $codes = $twoFactor->confirmSetup($user, (string) $request->string('code'), $request);

        return ApiResponse::data([
            'recovery_codes' => $codes,
        ]);
    }

    public function disable(
        DisableTwoFactorRequest $request,
        TwoFactorService $twoFactor,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $twoFactor->disable(
            $user,
            (string) $request->string('password'),
            (string) $request->string('code'),
            $request,
        );

        return ApiResponse::message('Two-factor authentication disabled. All sessions were signed out.');
    }
}
