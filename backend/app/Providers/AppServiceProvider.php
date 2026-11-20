<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        DB::prohibitDestructiveCommands($this->app->isProduction());

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // Trusted proxies: comma-separated IPs/CIDRs, or "*" behind a trusted
        // TLS-terminating load balancer. Configured here (not in
        // bootstrap/app.php) because the middleware callback runs before the
        // config repository is bound.
        $proxies = config('app.trusted_proxies');

        if ($proxies === '*') {
            TrustProxies::at('*');
        } elseif (is_string($proxies) && $proxies !== '') {
            TrustProxies::at(array_values(array_filter(array_map('trim', explode(',', $proxies)))));
        }

        PasswordRule::defaults(function (): PasswordRule {
            $rule = PasswordRule::min((int) config('identity.staff.password_min_length', 12))
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols();

            return config('identity.staff.password_uncompromised', true)
                ? $rule->uncompromised()
                : $rule;
        });

        RateLimiter::for('api', fn ($request) => Limit::perMinute(60)->by($request->user()?->getAuthIdentifier() ?: $request->ip()));

        // Identity module limiters (see Modules/Identity/Routes/api.php).
        RateLimiter::for('identity-oauth', fn ($request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('identity-staff-login', fn ($request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('identity-mfa', fn ($request) => Limit::perMinute(10)->by(
            $request->user()?->getAuthIdentifier() ?: $request->ip()
        ));
        RateLimiter::for('identity-invitation', fn ($request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('identity-password', fn ($request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('identity-sessions', fn ($request) => Limit::perMinute(30)->by(
            $request->user()?->getAuthIdentifier() ?: $request->ip()
        ));
    }
}
