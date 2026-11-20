<?php

declare(strict_types=1);

namespace App\Modules\Articles\Providers;

use App\Modules\Articles\Models\Article;
use App\Modules\Articles\Policies\ArticlePolicy;
use App\Modules\Articles\Services\ArticleAttachmentService;
use App\Modules\Articles\Services\ArticleWorkflowService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class ArticlesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ArticleWorkflowService::class);
        $this->app->singleton(ArticleAttachmentService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        Gate::policy(Article::class, ArticlePolicy::class);

        $this->registerRateLimiters();
    }

    private function registerRateLimiters(): void
    {
        RateLimiter::for('articles-public', fn (Request $request) => Limit::perMinute(60)
            ->by('articles-public|'.$request->ip()));

        RateLimiter::for('articles-user', fn (Request $request) => Limit::perMinute(30)
            ->by('articles-user|'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }
}
