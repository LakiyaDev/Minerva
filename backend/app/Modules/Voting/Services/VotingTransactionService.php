<?php

declare(strict_types=1);

namespace App\Modules\Voting\Services;

use App\Modules\Articles\Models\Article;
use App\Modules\Identity\Contracts\IdentityAccess;
use App\Modules\Identity\Models\User;
use App\Modules\Voting\Events\VoteRecorded;
use App\Modules\Voting\Events\VotingResultsUpdated;
use App\Modules\Voting\Exceptions\VotingException;
use App\Modules\Voting\Models\ArticleVoteSummary;
use App\Modules\Voting\Models\Vote;
use App\Modules\Voting\Models\VotingPeriod;
use App\Shared\Contracts\AuditLogger;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class VotingTransactionService
{
    public function __construct(
        private readonly IdentityAccess $identity,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Record a final, irrevocable vote inside a serialised database transaction.
     * Duplicate votes are rejected by the database unique index and mapped to
     * 409 Conflict; broadcast and domain events fire strictly after commit.
     *
     * @return array{vote: Vote, vote_count: int, period: VotingPeriod}
     */
    public function castVote(User $voter, string $articleIdentifier, ?Request $request = null): array
    {
        if (! $this->identity->isActive($voter) || ! $this->identity->hasPermission($voter, 'participant.vote')) {
            throw VotingException::ineligibleVoter();
        }

        $article = Article::query()
            ->published()
            ->where(function (Builder $query) use ($articleIdentifier): void {
                $query->where('id', $articleIdentifier)->orWhere('slug', $articleIdentifier);
            })
            ->first();

        if ($article === null) {
            throw VotingException::articleNotEligible();
        }

        $now = now();

        /** @var array{vote: Vote, vote_count: int, period: VotingPeriod} $result */
        $result = DB::transaction(function () use ($voter, $article, $now, $request): array {
            $period = VotingPeriod::query()
                ->where('is_active', true)
                ->orderByDesc('updated_at')
                ->orderByDesc('created_at')
                ->lockForUpdate()
                ->first();

            if ($period === null) {
                throw VotingException::periodNotConfigured();
            }

            if ($period->isUpcoming($now)) {
                throw VotingException::periodNotOpen('upcoming');
            }

            if (! $period->isOpen($now)) {
                throw VotingException::periodNotOpen('closed');
            }

            $vote = $this->insertVote($voter, $article, $period, $now);
            $voteCount = $this->incrementSummary($article, $now);

            $this->audit->record(
                'vote.cast',
                $voter,
                $vote,
                [
                    'article_id' => $article->getKey(),
                    'voting_period_id' => $period->getKey(),
                ],
                $request,
            );

            return [
                'vote' => $vote,
                'vote_count' => $voteCount,
                'period' => $period,
            ];
        });

        // Dispatch domain and real-time broadcast events only after commit.
        event(new VoteRecorded($result['vote'], $result['vote_count']));
        event(new VotingResultsUpdated(
            $result['period']->getKey(),
            $article->getKey(),
            $result['vote_count'],
            $now->toIso8601String(),
        ));

        return $result;
    }

    private function insertVote(User $voter, Article $article, VotingPeriod $period, CarbonInterface $now): Vote
    {
        try {
            return Vote::query()->create([
                'user_id' => $voter->getKey(),
                'article_id' => $article->getKey(),
                'voting_period_id' => $period->getKey(),
                'created_at' => $now,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw VotingException::alreadyVoted();
        } catch (QueryException $e) {
            if ($this->isDuplicateKeyException($e)) {
                throw VotingException::alreadyVoted();
            }

            throw $e;
        }
    }

    private function incrementSummary(Article $article, CarbonInterface $now): int
    {
        $summary = ArticleVoteSummary::query()
            ->where('article_id', $article->getKey())
            ->lockForUpdate()
            ->first();

        if ($summary !== null) {
            $summary->vote_count = $summary->vote_count + 1;
            $summary->updated_at = $now;
            $summary->save();

            return $summary->vote_count;
        }

        $authoritativeCount = Vote::query()
            ->where('article_id', $article->getKey())
            ->count();

        try {
            ArticleVoteSummary::query()->create([
                'article_id' => $article->getKey(),
                'vote_count' => $authoritativeCount,
                'updated_at' => $now,
            ]);

            return $authoritativeCount;
        } catch (UniqueConstraintViolationException|QueryException $e) {
            $existing = ArticleVoteSummary::query()
                ->where('article_id', $article->getKey())
                ->lockForUpdate()
                ->first();

            if ($existing === null) {
                throw $e;
            }

            $existing->vote_count = $authoritativeCount;
            $existing->updated_at = $now;
            $existing->save();

            return $authoritativeCount;
        }
    }

    private function isDuplicateKeyException(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? $e->getCode());
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        if ($driverCode === 1062) {
            return true;
        }

        if ($sqlState === '23000' && str_contains(strtolower($e->getMessage()), 'unique')) {
            return true;
        }

        return $sqlState === '23505';
    }
}
