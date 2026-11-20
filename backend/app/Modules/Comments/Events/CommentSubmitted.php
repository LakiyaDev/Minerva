<?php

declare(strict_types=1);

namespace App\Modules\Comments\Events;

use App\Modules\Comments\Models\Comment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CommentSubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Comment $comment) {}
}
