<?php

declare(strict_types=1);

namespace App\Modules\Comments\Services;

use App\Modules\Articles\Models\Article;
use App\Modules\Comments\Enums\CommentStatus;
use App\Modules\Comments\Events\CommentRemoved;
use App\Modules\Comments\Events\CommentSubmitted;
use App\Modules\Comments\Exceptions\CommentException;
use App\Modules\Comments\Models\Comment;
use App\Modules\Comments\Models\CommentModerationRecord;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;

final class CommentService
{
    public function submit(Article $article, User $author, string $body): Comment
    {
        if (! $article->isPublic()) {
            throw CommentException::articleNotPublished();
        }

        $comment = Comment::create([
            'article_id' => $article->getKey(),
            'user_id' => $author->getKey(),
            'body' => trim($body),
            'status' => CommentStatus::Visible,
        ]);

        CommentSubmitted::dispatch($comment);

        return $comment->load('author:id,name');
    }

    public function deleteOwn(Comment $comment): void
    {
        $comment->delete();
    }

    public function removeByModerator(Comment $comment, User $moderator, string $reason): Comment
    {
        if ($comment->isRemoved()) {
            throw CommentException::alreadyRemoved();
        }

        $cleanReason = trim($reason);

        DB::transaction(function () use ($comment, $moderator, $cleanReason): void {
            $comment->update([
                'status' => CommentStatus::Removed,
                'removed_at' => now(),
                'removed_by' => $moderator->getKey(),
            ]);

            CommentModerationRecord::create([
                'comment_id' => $comment->getKey(),
                'acted_by' => $moderator->getKey(),
                'action' => 'removed',
                'reason' => $cleanReason,
                'created_at' => now(),
            ]);
        });

        $comment->refresh()->load([
            'author:id,name',
            'article:id,title,slug',
            'moderationRecords.actor:id,name',
        ]);

        CommentRemoved::dispatch($comment, $moderator, $cleanReason);

        return $comment;
    }
}
