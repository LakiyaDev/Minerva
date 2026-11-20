<?php

declare(strict_types=1);

use App\Modules\Analytics\Providers\AnalyticsServiceProvider;
use App\Modules\Articles\Providers\ArticlesServiceProvider;
use App\Modules\Audit\Providers\AuditServiceProvider;
use App\Modules\Comments\Providers\CommentsServiceProvider;
use App\Modules\Content\Providers\ContentServiceProvider;
use App\Modules\Identity\Providers\IdentityServiceProvider;
use App\Modules\Integrations\Providers\IntegrationsServiceProvider;
use App\Modules\Voting\Providers\VotingServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    AuditServiceProvider::class,
    IdentityServiceProvider::class,
    ArticlesServiceProvider::class,
    CommentsServiceProvider::class,
    VotingServiceProvider::class,
    AnalyticsServiceProvider::class,
    ContentServiceProvider::class,
    IntegrationsServiceProvider::class,
];
