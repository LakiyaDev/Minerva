<?php

declare(strict_types=1);

namespace App\Modules\Comments\Events;

use App\Modules\Comments\Models\Comment;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CommentRemoved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Comment $comment,
        public readonly User $moderator,
        public readonly string $reason,
    ) {}
}
