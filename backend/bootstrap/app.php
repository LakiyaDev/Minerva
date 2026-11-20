<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Middleware\EnsureUserIsActive;
use App\Modules\Identity\Http\Middleware\RequireStaffTwoFactor;
use App\Shared\Exceptions\ApiExceptionRenderer;
use App\Shared\Http\Middleware\AuthorizePermission;
use App\Shared\Http\Middleware\ForceHttps;
use App\Shared\Http\Middleware\RequestId;
use App\Shared\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum: allow the first-party SPA origins in SANCTUM_STATEFUL_DOMAINS
        // to authenticate with the secure HttpOnly session cookie.
        $middleware->statefulApi();

        // This API has no guest page to redirect to. Laravel defaults to
        // route('login'), which does not exist here and turned every
        // unauthenticated request into a 500. Unauthenticated API calls must
        // resolve to a 401 JSON envelope instead.
        $middleware->redirectGuestsTo(fn (): ?string => null);

        // Trusted proxies are configured in AppServiceProvider::boot() — the
        // withMiddleware callback runs before the config repository exists.

        $middleware->append(RequestId::class);
        $middleware->append(ForceHttps::class);
        $middleware->append(SecurityHeaders::class);

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'permission' => AuthorizePermission::class,
            'staff.mfa' => RequireStaffTwoFactor::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(
            fn (Throwable $e, Request $request) => ApiExceptionRenderer::render($e, $request)
        );
    })->create();
