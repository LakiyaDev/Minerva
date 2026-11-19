<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Events\TwoFactorStatusChanged;
use App\Modules\Identity\Exceptions\IdentityException;
use App\Modules\Identity\Models\User;
use App\Shared\Contracts\AuditLogger;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

final class TwoFactorService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SessionService $sessions,
    ) {}

    /**
     * @return array{secret: string, otpauth_url: string, qr_svg: string}
     */
    public function beginSetup(User $user, Request $request): array
    {
        if (! $user->isStaff()) {
            throw IdentityException::twoFactorStaffOnly();
        }

        if ($user->hasConfirmedTwoFactor()) {
            throw IdentityException::mfaAlreadyEnabled();
        }

        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey(32);

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
        ])->save();

        $otpauthUrl = $google2fa->getQRCodeUrl(
            (string) config('identity.staff.totp_issuer', 'Minerva Digital Platform'),
            $user->email,
            $secret,
        );

        $this->audit->record('auth.mfa.setup_started', $user, $user, [], $request);

        return [
            'secret' => $secret,
            'otpauth_url' => $otpauthUrl,
            'qr_svg' => $this->qrDataUri($otpauthUrl),
        ];
    }

    /**
     * @return array<int, string> Plaintext recovery codes (shown exactly once)
     */
    public function confirmSetup(User $user, string $code, Request $request): array
    {
        if (! $user->isStaff()) {
            throw IdentityException::twoFactorStaffOnly();
        }

        if ($user->hasConfirmedTwoFactor()) {
            throw IdentityException::mfaAlreadyEnabled();
        }

        if ($user->two_factor_secret === null) {
            throw IdentityException::mfaNotEnabled();
        }

        if (! $this->verifyTotp($user, $code)) {
            throw IdentityException::invalidMfaCode();
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => array_map(
                fn (string $recoveryCode): string => Hash::make($recoveryCode),
                $recoveryCodes,
            ),
            'two_factor_confirmed_at' => now(),
        ])->save();

        TwoFactorStatusChanged::dispatch($user, true);

        $this->audit->record('auth.mfa.enabled', $user, $user, [], $request);

        return $recoveryCodes;
    }

    public function verifyForLogin(User $user, string $code, Request $request): bool
    {
        if (! $user->hasConfirmedTwoFactor()) {
            throw IdentityException::mfaNotEnabled();
        }

        $code = trim($code);

        if ($this->verifyTotp($user, $code)) {
            return true;
        }

        return $this->consumeRecoveryCode($user, $code, $request);
    }

    public function disable(User $user, string $password, string $code, Request $request): void
    {
        if (! $user->isStaff()) {
            throw IdentityException::twoFactorStaffOnly();
        }

        if (! $user->hasConfirmedTwoFactor()) {
            throw IdentityException::mfaNotEnabled();
        }

        if (! $user->hasPasswordCredential() || ! Hash::check($password, (string) $user->password)) {
            throw IdentityException::invalidCredentials();
        }

        if (! $this->verifyForLogin($user, $code, $request)) {
            throw IdentityException::invalidMfaCode();
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        // Removing a factor invalidates every existing session.
        $this->sessions->revokeAllSessions($user);

        TwoFactorStatusChanged::dispatch($user, false);

        $this->audit->record('auth.mfa.disabled', $user, $user, [], $request);
    }

    private function verifyTotp(User $user, string $code): bool
    {
        $secret = $user->two_factor_secret;

        if (! is_string($secret) || $secret === '' || $code === '') {
            return false;
        }

        $normalized = preg_replace('/\s+/', '', $code) ?? '';

        return (new Google2FA)->verifyKey(
            $secret,
            $normalized,
            (int) config('identity.staff.totp_window', 1),
        );
    }

    private function consumeRecoveryCode(User $user, string $code, Request $request): bool
    {
        $hashes = $user->two_factor_recovery_codes;

        if (! is_array($hashes) || $code === '') {
            return false;
        }

        $normalized = Str::upper($code);

        foreach ($hashes as $index => $hash) {
            if (! is_string($hash) || ! Hash::check($normalized, $hash)) {
                continue;
            }

            unset($hashes[$index]);

            $user->forceFill([
                'two_factor_recovery_codes' => array_values($hashes),
            ])->save();

            $this->audit->record('auth.mfa.recovery_code_used', $user, $user, [
                'remaining_codes' => count($hashes),
            ], $request);

            return true;
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function generateRecoveryCodes(): array
    {
        $count = (int) config('identity.staff.recovery_code_count', 8);

        return array_map(
            fn (): string => Str::upper(Str::random(5)).'-'.Str::upper(Str::random(5)),
            range(1, $count),
        );
    }

    private function qrDataUri(string $otpauthUrl): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(256, 1),
            new SvgImageBackEnd,
        );

        $svg = (new Writer($renderer))->writeString($otpauthUrl);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
