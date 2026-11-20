<?php

declare(strict_types=1);

namespace App\Modules\Voting\Providers;

use App\Modules\Voting\Models\Vote;
use App\Modules\Voting\Policies\VotePolicy;
use App\Modules\Voting\Services\VotingPeriodService;
use App\Modules\Voting\Services\VotingQueryService;
use App\Modules\Voting\Services\VotingTransactionService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class VotingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(VotingPeriodService::class);
        $this->app->singleton(VotingTransactionService::class);
        $this->app->singleton(VotingQueryService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        Gate::policy(Vote::class, VotePolicy::class);

        $this->registerRateLimiters();
    }

    private function registerRateLimiters(): void
    {
        RateLimiter::for('voting-cast', fn (Request $request) => Limit::perMinute(5)
            ->by('voting-cast|'.($request->user()?->getAuthIdentifier() ?? '').'|'.$request->ip()));

        RateLimiter::for('voting-read', fn (Request $request) => Limit::perMinute(60)
            ->by('voting-read|'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }
}
