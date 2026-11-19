<?php

declare(strict_types=1);

namespace App\Modules\Identity\Exceptions;

use App\Shared\Contracts\ApiError;
use Illuminate\Support\Carbon;
use RuntimeException;

class IdentityException extends RuntimeException implements ApiError
{
    /**
     * @param  array<string, array<int, string>>  $errors
     * @param  array<string, string>  $headers
     */
    public function __construct(
        private readonly int $status,
        private readonly string $errorCode,
        string $message,
        private readonly array $errors = [],
        private readonly array $headers = [],
    ) {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function message(): string
    {
        return $this->getMessage();
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public static function invalidCredentials(): self
    {
        return new self(401, 'invalid_credentials', 'The provided credentials are incorrect.');
    }

    public static function accountInactive(): self
    {
        return new self(403, 'account_inactive', 'This account is not active.');
    }

    public static function accountLocked(Carbon $until): self
    {
        $retryAfter = max(1, (int) now()->diffInSeconds($until));

        return new self(
            423,
            'account_locked',
            'Too many failed attempts. This account is temporarily locked.',
            headers: ['Retry-After' => (string) $retryAfter],
        );
    }

    public static function staffRoleRequired(): self
    {
        return new self(403, 'staff_role_required', 'This endpoint is only available to staff accounts.');
    }

    public static function googleEmailUnverified(): self
    {
        return new self(403, 'google_email_unverified', 'Google did not verify this email address.');
    }

    public static function googleStaffAccount(): self
    {
        return new self(403, 'staff_use_password', 'Staff accounts must sign in with their staff credentials.');
    }

    public static function invalidMfaChallenge(): self
    {
        return new self(401, 'mfa_challenge_invalid', 'The verification session is invalid or has expired.');
    }

    public static function invalidMfaCode(): self
    {
        return new self(422, 'mfa_code_invalid', 'The verification code is invalid.', [
            'code' => ['The verification code is invalid.'],
        ]);
    }

    public static function mfaNotEnabled(): self
    {
        return new self(409, 'mfa_not_enabled', 'Two-factor authentication is not enabled for this account.');
    }

    public static function mfaEnrollmentRequired(): self
    {
        return new self(403, 'mfa_enrollment_required', 'Two-factor authentication must be enabled before accessing this resource.');
    }

    public static function twoFactorStaffOnly(): self
    {
        return new self(403, 'two_factor_staff_only', 'Two-factor authentication is only available to staff accounts.');
    }

    public static function mfaAlreadyEnabled(): self
    {
        return new self(409, 'mfa_already_enabled', 'Two-factor authentication is already enabled.');
    }

    public static function invalidInvitation(): self
    {
        return new self(422, 'invitation_invalid', 'This invitation is invalid or has expired.', [
            'token' => ['This invitation is invalid or has expired.'],
        ]);
    }

    public static function roleNotAssignable(string $role): self
    {
        return new self(422, 'role_not_assignable', "The role [{$role}] cannot be assigned.", [
            'role' => ["The role [{$role}] cannot be assigned."],
        ]);
    }

    public static function lastAdministrator(): self
    {
        return new self(409, 'last_administrator', 'The last active administrator cannot be removed or demoted.');
    }

    public static function selfRoleChange(): self
    {
        return new self(403, 'self_role_change', 'You cannot change your own roles or permissions.');
    }

    public static function conflict(string $message): self
    {
        return new self(409, 'conflict', $message);
    }

    public static function notFound(string $message = 'Resource not found.'): self
    {
        return new self(404, 'not_found', $message);
    }
}
