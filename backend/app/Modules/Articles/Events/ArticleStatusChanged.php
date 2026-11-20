<?php

declare(strict_types=1);

namespace App\Modules\Articles\Events;

use App\Modules\Articles\Enums\ArticleStatus;
use App\Modules\Articles\Models\Article;
use App\Modules\Identity\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Base editorial event. Dispatched for every workflow transition so that
 * Analytics, Comments or Content modules can react without importing this
 * module's internals.
 */
class ArticleStatusChanged implements ShouldDispatchAfterCommit
{
    public function __construct(
        public readonly Article $article,
        public readonly ?ArticleStatus $from,
        public readonly ArticleStatus $to,
        public readonly ?User $actor,
        public readonly ?string $reason = null,
    ) {}
}
