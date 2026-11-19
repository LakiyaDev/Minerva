<?php

declare(strict_types=1);

namespace App\Modules\Audit\Providers;

use App\Modules\Audit\Services\DatabaseAuditLogger;
use App\Shared\Contracts\AuditLogger;
use Illuminate\Support\ServiceProvider;

final class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuditLogger::class, DatabaseAuditLogger::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }
}
