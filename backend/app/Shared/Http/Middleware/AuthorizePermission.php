<?php

declare(strict_types=1);

namespace App\Shared\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthorizePermission
{
    /**
     * Allow the request when the authenticated subject holds at least one of
     * the required permissions. Authorization is always re-checked here — the
     * frontend never decides access.
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if ($user === null) {
            throw new AuthenticationException;
        }

        if (! method_exists($user, 'hasAnyPermission') || ! $user->hasAnyPermission($permissions)) {
            throw new AuthorizationException('You do not have the required permission.');
        }

        return $next($request);
    }
}
