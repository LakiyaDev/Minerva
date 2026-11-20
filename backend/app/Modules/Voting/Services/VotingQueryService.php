<?php

declare(strict_types=1);

namespace App\Modules\Voting\Services;

use App\Modules\Articles\Models\Article;
use App\Modules\Identity\Models\User;
use App\Modules\Voting\Exceptions\VotingException;
use App\Modules\Voting\Models\ArticleVoteSummary;
use App\Modules\Voting\Models\Vote;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class VotingQueryService
{
    public function __construct(private readonly VotingPeriodService $periods) {}

    public function resolvePublishedArticle(string $identifier): Article
    {
        $article = Article::query()
            ->published()
            ->where(function (Builder $query) use ($identifier): void {
                $query->where('id', $identifier)->orWhere('slug', $identifier);
            })
            ->first();

        if ($article === null) {
            throw VotingException::articleNotEligible();
        }

        return $article;
    }

    /**
     * @return array<string, mixed>
     */
    public function articleResults(Article $article, User $viewer): array
    {
        $board = $this->leaderboard($viewer);
        $entry = collect($board['entries'])->firstWhere('article_id', $article->getKey());

        $period = $this->periods->current();
        $summary = ArticleVoteSummary::query()->find($article->getKey());

        return [
            'article_id' => $article->getKey(),
            'slug' => $article->slug,
            'title' => $article->title,
            'vote_count' => $entry['vote_count'] ?? (int) ($summary?->vote_count ?? 0),
            'rank' => $entry['rank'] ?? null,
            'has_voted' => $entry['has_voted'] ?? Vote::query()
                ->where('user_id', $viewer->getKey())
                ->where('article_id', $article->getKey())
                ->exists(),
            'voting_status' => $period?->windowStatus() ?? 'closed',
            'updated_at' => ($summary?->updated_at ?? now())->toIso8601String(),
        ];
    }

    /**
     * @return array{entries: array<int, array<string, mixed>>, total_votes: int}
     */
    public function leaderboard(?User $viewer = null): array
    {
        $articles = Article::query()
            ->published()
            ->with(['category:id,name,slug', 'author:id,name'])
            ->leftJoin('article_vote_summaries', 'articles.id', '=', 'article_vote_summaries.article_id')
            ->select([
                'articles.*',
                'article_vote_summaries.vote_count as summary_vote_count',
            ])
            ->orderByRaw('COALESCE(article_vote_summaries.vote_count, 0) DESC')
            ->orderByDesc('articles.published_at')
            ->get();

        $votedIds = $viewer !== null
            ? Vote::query()
                ->where('user_id', $viewer->getKey())
                ->pluck('article_id')
                ->flip()
                ->all()
            : [];

        $totalVotes = 0;
        $entries = [];

        foreach ($articles as $index => $article) {
            $voteCount = (int) ($article->getAttribute('summary_vote_count') ?? 0);
            $totalVotes += $voteCount;

            $entries[] = [
                'rank' => $index + 1,
                'article_id' => $article->getKey(),
                'title' => $article->title,
                'slug' => $article->slug,
                'category' => $article->category ? [
                    'id' => $article->category->getKey(),
                    'name' => $article->category->name,
                    'slug' => $article->category->slug,
                ] : null,
                'author' => $article->author ? [
                    'id' => $article->author->getKey(),
                    'name' => $article->author->name,
                ] : null,
                'vote_count' => $voteCount,
                'has_voted' => array_key_exists($article->getKey(), $votedIds),
                'published_at' => $article->published_at?->toIso8601String(),
            ];
        }

        return [
            'entries' => $entries,
            'total_votes' => $totalVotes,
        ];
    }

    /**
     * @return Collection<int, Vote>
     */
    public function userVotes(User $user): Collection
    {
        return Vote::query()
            ->where('user_id', $user->getKey())
            ->with([
                'article:id,title,slug,category_id,author_id,status,published_at',
                'article.category:id,name,slug',
                'article.author:id,name',
                'votingPeriod:id,name',
            ])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function statistics(): array
    {
        $board = $this->leaderboard();
        $entries = $board['entries'];

        $totalVotes = Vote::query()->count();
        $uniqueVoters = Vote::query()->distinct('user_id')->count('user_id');
        $publishedCount = count($entries);
        $activePeriod = $this->periods->current();

        $byCategory = collect($entries)
            ->groupBy(fn (array $entry): string => (string) ($entry['category']['name'] ?? 'Uncategorized'))
            ->map(fn ($items, string $categoryName): array => [
                'name' => $categoryName,
                'votes' => (int) collect($items)->sum('vote_count'),
                'articles_count' => count($items),
            ])
            ->sortByDesc('votes')
            ->values()
            ->all();

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
                'day' => $day->format('M d'),
                'votes' => $countsByDay[$dayKey] ?? 0,
            ];
        }

        return [
            'total_votes' => $totalVotes,
            'unique_voters' => $uniqueVoters,
            'published_articles_count' => $publishedCount,
            'active_period' => $activePeriod,
            'top_articles' => array_slice($entries, 0, 10),
            'by_category' => $byCategory,
            'daily_activity' => $dailyActivity,
        ];
    }
}
