<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
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
            \Illuminate\Support\Facades\URL::forceScheme('https');
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
    }
}
