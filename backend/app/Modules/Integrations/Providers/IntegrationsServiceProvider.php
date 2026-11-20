<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Service-to-service API keys — schema v1.1 §4.6.1.
 */
final class IntegrationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }
}
