<?php

declare(strict_types=1);

namespace App\Modules\Comments\Providers;

use App\Modules\Comments\Models\Comment;
use App\Modules\Comments\Policies\CommentPolicy;
use App\Modules\Comments\Services\CommentService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class CommentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CommentService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        Gate::policy(Comment::class, CommentPolicy::class);

        $this->registerRateLimiters();
    }

    private function registerRateLimiters(): void
    {
        RateLimiter::for('comments-public', fn (Request $request) => Limit::perMinute(60)
            ->by('comments-public|'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('comments-write', fn (Request $request) => Limit::perMinute(20)
            ->by('comments-write|'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }
}
