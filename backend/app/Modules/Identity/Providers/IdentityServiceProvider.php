<?php

declare(strict_types=1);

namespace App\Modules\Identity\Providers;

use App\Modules\Identity\Console\CreateAdministratorCommand;
use App\Modules\Identity\Contracts\IdentityAccess;
use App\Modules\Identity\Models\PersonalAccessToken;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Policies\UserPolicy;
use App\Modules\Identity\Services\IdentityAccessService;
use App\Modules\Identity\Services\PermissionResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

final class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PermissionResolver::class);
        $this->app->singleton(IdentityAccess::class, IdentityAccessService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        Gate::policy(User::class, UserPolicy::class);

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        $this->registerRateLimiters();

        if ($this->app->runningInConsole()) {
            $this->commands([
                CreateAdministratorCommand::class,
            ]);
        }
    }

    private function registerRateLimiters(): void
    {
        RateLimiter::for('identity-oauth', fn (Request $request) => Limit::perMinute(10)
            ->by('oauth|'.$request->ip()));

        RateLimiter::for('identity-staff-login', function (Request $request) {
            $email = $request->input('email');

            return Limit::perMinute(5)
                ->by('staff-login|'.(is_string($email) ? strtolower($email) : '').'|'.$request->ip());
        });

        RateLimiter::for('identity-mfa', fn (Request $request) => Limit::perMinute(5)
            ->by('mfa|'.$request->ip()));

        RateLimiter::for('identity-invitation', fn (Request $request) => Limit::perHour(5)
            ->by('invitation|'.$request->ip()));

        RateLimiter::for('identity-password', function (Request $request) {
            $email = $request->input('email');

            return Limit::perMinute(5)
                ->by('password|'.(is_string($email) ? strtolower($email) : '').'|'.$request->ip());
        });

        RateLimiter::for('identity-sessions', fn (Request $request) => Limit::perMinute(10)
            ->by('sessions|'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }
}
