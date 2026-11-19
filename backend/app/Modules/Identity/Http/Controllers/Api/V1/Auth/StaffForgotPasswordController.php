<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Api\V1\Auth;

use App\Modules\Identity\Http\Requests\ForgotStaffPasswordRequest;
use App\Modules\Identity\Models\User;
use App\Shared\Http\Responses\ApiResponse;
use App\Shared\Support\EmailNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

final class StaffForgotPasswordController
{
    /**
     * Always return the same response so the endpoint cannot be used to
     * enumerate staff accounts.
     */
    public function __invoke(ForgotStaffPasswordRequest $request): JsonResponse
    {
        $email = EmailNormalizer::normalize((string) $request->string('email'));
        $user = User::query()->where('email', $email)->first();

        if (
            $user !== null
            && $user->isActive()
            && $user->isStaff()
            && $user->hasPasswordCredential()
        ) {
            Password::sendResetLink(['email' => $email]);
        }

        return ApiResponse::message(
            'If a staff account exists for this address, a password reset link has been sent.',
            202,
        );
    }
}
