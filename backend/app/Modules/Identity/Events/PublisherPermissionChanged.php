<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use App\Modules\Identity\Models\PublisherPermission;
use App\Modules\Identity\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PublisherPermissionChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $change,
        public readonly ?PublisherPermission $grant = null,
        public readonly ?User $actor = null,
    ) {}
}
