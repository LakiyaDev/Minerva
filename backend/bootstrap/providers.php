<?php

declare(strict_types=1);

return [
    App\Providers\AppServiceProvider::class,
    App\Modules\Audit\Providers\AuditServiceProvider::class,
    App\Modules\Identity\Providers\IdentityServiceProvider::class,
];
