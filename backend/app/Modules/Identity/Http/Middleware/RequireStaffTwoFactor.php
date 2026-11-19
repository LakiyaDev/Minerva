<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Exceptions\IdentityException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireStaffTwoFactor
{
    /**
     * Staff endpoints (admin/publisher management) require a confirmed TOTP
     * factor. This is enforced server-side even if the UI is bypassed.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user !== null
            && method_exists($user, 'isStaff')
            && $user->isStaff()
            && (bool) config('identity.staff.mfa_required', true)
            && (! method_exists($user, 'hasConfirmedTwoFactor') || ! $user->hasConfirmedTwoFactor())
        ) {
            throw IdentityException::mfaEnrollmentRequired();
        }

        return $next($request);
    }
}
