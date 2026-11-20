<?php

declare(strict_types=1);

namespace App\Modules\Comments\Policies;

use App\Modules\Comments\Models\Comment;
use App\Modules\Identity\Models\User;

final class CommentPolicy
{
    public function delete(User $user, Comment $comment): bool
    {
        return $comment->user_id === $user->getKey();
    }

    public function moderate(User $user): bool
    {
        return $user->hasPermission('comment.moderate');
    }
}
