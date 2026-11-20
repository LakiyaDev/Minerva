<?php

declare(strict_types=1);

namespace App\Modules\Articles\Policies;

use App\Modules\Articles\Models\Article;
use App\Modules\Identity\Models\User;

final class ArticlePolicy
{
    /**
     * Public visibility is a status question, never an identity question.
     */
    public function viewPublic(?User $user, Article $article): bool
    {
        return $article->status->isPublic();
    }

    public function viewOwn(User $user, Article $article): bool
    {
        return $this->owns($user, $article)
            || $user->hasAnyPermission(['article.publish_any', 'article.unpublish']);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('article.create');
    }

    public function updateOwn(User $user, Article $article): bool
    {
        return $this->owns($user, $article)
            && $article->status->isEditableByOwner()
            && $user->hasPermission('article.update_own');
    }

    public function publishOwn(User $user, Article $article): bool
    {
        return $this->owns($user, $article)
            && $user->hasPermission('article.publish_own');
    }

    public function publishAny(User $user): bool
    {
        return $user->hasPermission('article.publish_any');
    }

    public function unpublish(User $user): bool
    {
        return $user->hasPermission('article.unpublish');
    }

    public function feature(User $user): bool
    {
        return $user->hasPermission('article.feature');
    }

    public function uploadAttachment(User $user, Article $article): bool
    {
        if ($user->hasAnyPermission(['article.publish_any', 'article.unpublish'])) {
            return true;
        }

        return $this->owns($user, $article)
            && $article->status->isEditableByOwner()
            && $user->hasPermission('article.update_own');
    }

    public function deleteAttachment(User $user, Article $article): bool
    {
        return $this->uploadAttachment($user, $article);
    }

    public function downloadAttachment(?User $user, Article $article): bool
    {
        if ($article->isPublic()) {
            return true;
        }

        if (! $user instanceof User) {
            return false;
        }

        return $this->viewOwn($user, $article);
    }

    private function owns(User $user, Article $article): bool
    {
        return $article->author_id !== null && $article->author_id === $user->getKey();
    }
}
