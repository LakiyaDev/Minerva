<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Exceptions\IdentityException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && method_exists($user, 'isActive') && ! $user->isActive()) {
            throw IdentityException::accountInactive();
        }

        return $next($request);
    }
}
