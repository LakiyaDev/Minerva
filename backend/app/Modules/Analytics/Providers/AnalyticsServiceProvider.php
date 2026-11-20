<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Providers;

use App\Modules\Analytics\Policies\AnalyticsPolicy;
use App\Modules\Analytics\Services\AnalyticsService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class AnalyticsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AnalyticsService::class);
    }

    public function boot(): void
    {
        Gate::policy(AnalyticsPolicy::class, AnalyticsPolicy::class);
    }
}
