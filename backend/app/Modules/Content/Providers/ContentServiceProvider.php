<?php

declare(strict_types=1);

namespace App\Modules\Content\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Public website content (workshops, timeline, sponsors, committee, magazine)
 * and dashboard-managed platform settings — schema v1.1 §4.5 and §4.8.
 */
final class ContentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }
}
