<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Services;

use App\Modules\Articles\Enums\ArticleStatus;
use App\Modules\Articles\Models\Article;
use App\Modules\Articles\Models\ArticleCategory;
use App\Modules\Comments\Enums\CommentStatus;
use App\Modules\Comments\Models\Comment;
use App\Modules\Voting\Models\Vote;
use App\Modules\Voting\Services\VotingPeriodService;
use Carbon\Carbon;

final class AnalyticsService
{
    public function __construct(private readonly VotingPeriodService $votingPeriods) {}

    /**
     * Compute analytics for a single article (FR-50, FR-51).
     *
     * @return array<string, mixed>
     */
    public function getArticleAnalytics(Article $article): array
    {
        $article->loadMissing(['category:id,name,slug', 'author:id,name']);

        $totalVotes = Vote::query()->where('article_id', $article->getKey())->count();

        // Rank among all published articles (by vote count descending).
        $rank = null;
        if ($article->status === ArticleStatus::Published) {
            $higherCount = Article::query()
                ->where('status', ArticleStatus::Published->value)
                ->whereNotNull('published_at')
                ->where('id', '!=', $article->getKey())
                ->whereRaw('(SELECT COUNT(*) FROM votes WHERE votes.article_id = articles.id) > ?', [$totalVotes])
                ->count();

            $rank = $higherCount + 1;
        }

        // Comments engagement
        $commentsQuery = Comment::query()->where('article_id', $article->getKey());
        $totalComments = (clone $commentsQuery)->count();
        $visibleComments = (clone $commentsQuery)->where('status', CommentStatus::Visible->value)->count();
        $removedComments = (clone $commentsQuery)->where('status', CommentStatus::Removed->value)->count();

        // Category & Platform benchmarks
        $categoryAverageVotes = 0.0;
        if ($article->category_id) {
            $catArticlesCount = Article::query()
                ->where('category_id', $article->category_id)
                ->where('status', ArticleStatus::Published->value)
                ->count();

            if ($catArticlesCount > 0) {
                $catTotalVotes = Vote::query()
                    ->whereIn(
                        'article_id',
                        Article::query()
                            ->where('category_id', $article->category_id)
                            ->where('status', ArticleStatus::Published->value)
                            ->select('id'),
                    )
                    ->count();

                $categoryAverageVotes = round($catTotalVotes / $catArticlesCount, 1);
            }
        }

        $allPublishedCount = Article::query()->where('status', ArticleStatus::Published->value)->count();
        $platformAverageVotes = $allPublishedCount > 0
            ? round(Vote::query()->count() / $allPublishedCount, 1)
            : 0.0;

        // 14-day daily vote trend for this article
        $windowStart = now()->subDays(13)->startOfDay();
        $rawTimestamps = Vote::query()
            ->where('article_id', $article->getKey())
            ->where('created_at', '>=', $windowStart)
            ->pluck('created_at');

        $countsByDay = [];
        foreach ($rawTimestamps as $timestamp) {
            $dayKey = Carbon::parse($timestamp)->format('Y-m-d');
            $countsByDay[$dayKey] = ($countsByDay[$dayKey] ?? 0) + 1;
        }

        $dailyVotes = [];
        for ($offset = 13; $offset >= 0; $offset--) {
            $day = now()->subDays($offset);
            $dayKey = $day->format('Y-m-d');
            $dailyVotes[] = [
                'date' => $dayKey,
                'label' => $day->format('M d'),
                'votes' => $countsByDay[$dayKey] ?? 0,
            ];
        }

        return [
            'article' => [
                'id' => $article->getKey(),
                'title' => $article->title,
                'slug' => $article->slug,
                'status' => $article->status->value,
                'status_label' => $article->status->label(),
                'is_featured' => $article->is_featured,
                'published_at' => $article->published_at?->toIso8601String(),
                'category' => $article->category ? [
                    'id' => $article->category->getKey(),
                    'name' => $article->category->name,
                    'slug' => $article->category->slug,
                ] : null,
                'author' => $article->author ? [
                    'id' => $article->author->getKey(),
                    'name' => $article->author->name,
                ] : null,
            ],
            'engagement' => [
                'total_votes' => $totalVotes,
                'rank' => $rank,
                'total_comments' => $totalComments,
                'visible_comments_count' => $visibleComments,
                'removed_comments_count' => $removedComments,
            ],
            'benchmarks' => [
                'category_average_votes' => $categoryAverageVotes,
                'platform_average_votes' => $platformAverageVotes,
            ],
            'daily_votes' => $dailyVotes,
        ];
    }

    /**
     * Compute platform-wide analytics for administrators (FR-53).
     *
     * @return array<string, mixed>
     */
    public function getPlatformAnalytics(): array
    {
        // Articles breakdown
        $totalArticles = Article::query()->count();
        $articlesByStatus = [];
        foreach (ArticleStatus::cases() as $case) {
            $articlesByStatus[$case->value] = Article::query()->where('status', $case->value)->count();
        }
        $featuredCount = Article::query()->where('is_featured', true)->count();

        // Categories distribution
        $categories = ArticleCategory::query()->get()->map(function (ArticleCategory $cat): array {
            $articlesCount = Article::query()->where('category_id', $cat->getKey())->count();
            $publishedCount = Article::query()
                ->where('category_id', $cat->getKey())
                ->where('status', ArticleStatus::Published->value)
                ->count();

            $votesCount = Vote::query()
                ->whereIn(
                    'article_id',
                    Article::query()->where('category_id', $cat->getKey())->select('id'),
                )
                ->count();

            return [
                'id' => $cat->getKey(),
                'name' => $cat->name,
                'slug' => $cat->slug,
                'articles_count' => $articlesCount,
                'published_count' => $publishedCount,
                'votes_count' => $votesCount,
            ];
        })->values()->all();

        $totalPlatformVotes = Vote::query()->count();
        $uniqueVoters = Vote::query()->distinct('user_id')->count('user_id');

        // Append vote share % to each category
        foreach ($categories as &$catData) {
            $catData['vote_share_percent'] = $totalPlatformVotes > 0
                ? round(($catData['votes_count'] / $totalPlatformVotes) * 100, 1)
                : 0.0;
        }
        unset($catData);

        // Voting trend for last 14 days
        $windowStart = now()->subDays(13)->startOfDay();
        $rawTimestamps = Vote::query()
            ->where('created_at', '>=', $windowStart)
            ->pluck('created_at');

        $countsByDay = [];
        foreach ($rawTimestamps as $timestamp) {
            $dayKey = Carbon::parse($timestamp)->format('Y-m-d');
            $countsByDay[$dayKey] = ($countsByDay[$dayKey] ?? 0) + 1;
        }

        $dailyActivity = [];
        for ($offset = 13; $offset >= 0; $offset--) {
            $day = now()->subDays($offset);
            $dayKey = $day->format('Y-m-d');
            $dailyActivity[] = [
                'date' => $dayKey,
                'label' => $day->format('M d'),
                'votes' => $countsByDay[$dayKey] ?? 0,
            ];
        }

        // Comments overview
        $totalComments = Comment::query()->count();
        $visibleComments = Comment::query()->where('status', CommentStatus::Visible->value)->count();
        $removedComments = Comment::query()->where('status', CommentStatus::Removed->value)->count();
        $moderationRate = $totalComments > 0
            ? round(($removedComments / $totalComments) * 100, 1)
            : 0.0;

        // Top 6 performing articles
        $topArticles = Article::query()
            ->where('status', ArticleStatus::Published->value)
            ->with(['author:id,name', 'category:id,name,slug'])
            ->withCount('attachments')
            ->selectSub(
                Vote::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('votes.article_id', 'articles.id'),
                'votes_count',
            )
            ->orderByDesc('votes_count')
            ->limit(6)
            ->get()
            ->map(fn (Article $a): array => [
                'id' => $a->getKey(),
                'title' => $a->title,
                'slug' => $a->slug,
                'votes_count' => (int) ($a->votes_count ?? 0),
                'author' => $a->author ? ['id' => $a->author->getKey(), 'name' => $a->author->name] : null,
                'category' => $a->category ? ['id' => $a->category->getKey(), 'name' => $a->category->name] : null,
            ])
            ->values()
            ->all();

        $activePeriod = $this->votingPeriods->current();

        return [
            'articles_summary' => [
                'total' => $totalArticles,
                'by_status' => $articlesByStatus,
                'featured_count' => $featuredCount,
            ],
            'categories' => $categories,
            'voting' => [
                'total_votes' => $totalPlatformVotes,
                'unique_voters' => $uniqueVoters,
                'active_period' => $activePeriod ? [
                    'id' => $activePeriod->getKey(),
                    'name' => $activePeriod->name,
                    'is_active' => $activePeriod->is_active,
                    'starts_at' => $activePeriod->starts_at?->toIso8601String(),
                    'ends_at' => $activePeriod->ends_at?->toIso8601String(),
                ] : null,
                'daily_trend' => $dailyActivity,
            ],
            'community' => [
                'total_comments' => $totalComments,
                'visible_comments' => $visibleComments,
                'removed_comments' => $removedComments,
                'moderation_rate_percent' => $moderationRate,
            ],
            'top_performing_articles' => $topArticles,
        ];
    }
}
