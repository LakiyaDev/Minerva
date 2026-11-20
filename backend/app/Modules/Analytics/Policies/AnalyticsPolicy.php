<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Policies;

use App\Modules\Articles\Models\Article;
use App\Modules\Identity\Models\User;

final class AnalyticsPolicy
{
    /**
     * A publisher can view analytics for their own articles (FR-50, FR-52).
     * Staff with analytics.view_all can view any article's analytics.
     */
    public function viewArticleAnalytics(User $user, Article $article): bool
    {
        if ($user->hasPermission('analytics.view_all')) {
            return true;
        }

        return $article->author_id !== null
            && $article->author_id === $user->getKey()
            && $user->hasPermission('analytics.view_own');
    }

    /**
     * Platform-wide analytics requires analytics.view_all (FR-53).
     */
    public function viewPlatformAnalytics(User $user): bool
    {
        return $user->hasPermission('analytics.view_all');
    }
}
