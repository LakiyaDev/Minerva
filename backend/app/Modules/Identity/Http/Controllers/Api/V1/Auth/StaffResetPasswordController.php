<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Api\V1\Auth;

use App\Modules\Identity\Exceptions\IdentityException;
use App\Modules\Identity\Http\Requests\ResetStaffPasswordRequest;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\SessionService;
use App\Shared\Contracts\AuditLogger;
use App\Shared\Http\Responses\ApiResponse;
use App\Shared\Support\EmailNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

final class StaffResetPasswordController
{
    public function __invoke(
        ResetStaffPasswordRequest $request,
        SessionService $sessions,
        AuditLogger $audit,
    ): JsonResponse {
        $credentials = [
            'email' => EmailNormalizer::normalize((string) $request->string('email')),
            'password' => (string) $request->string('password'),
            'password_confirmation' => (string) $request->string('password_confirmation'),
            'token' => (string) $request->string('token'),
        ];

        $status = Password::reset($credentials, function (User $user, string $password) use ($sessions, $audit, $request): void {
            $user->forceFill([
                'password' => $password,
                'failed_login_attempts' => 0,
                'locked_until' => null,
                'remember_token' => Str::random(60),
            ])->save();

            // A reset invalidates every existing session and token.
            $sessions->revokeAllSessions($user);

            $audit->record('auth.staff.password_reset', $user, $user, [], $request);
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw new IdentityException(
                422,
                'password_reset_failed',
                'The password reset link is invalid or has expired.',
                ['email' => ['The password reset link is invalid or has expired.']],
            );
        }

        return ApiResponse::message('Password reset successfully.');
    }
}
