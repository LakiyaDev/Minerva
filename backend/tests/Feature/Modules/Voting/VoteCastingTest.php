<?php

declare(strict_types=1);

use App\Modules\Articles\Database\Seeders\ArticleCategorySeeder;
use App\Modules\Articles\Enums\ArticleStatus;
use App\Modules\Articles\Models\Article;
use App\Modules\Identity\Models\User;
use App\Modules\Voting\Events\VoteRecorded;
use App\Modules\Voting\Events\VotingResultsUpdated;
use App\Modules\Voting\Exceptions\VotingException;
use App\Modules\Voting\Models\ArticleVoteSummary;
use App\Modules\Voting\Models\Vote;
use App\Modules\Voting\Models\VotingPeriod;
use App\Modules\Voting\Services\VotingTransactionService;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    seedIdentity();
    $this->seed(ArticleCategorySeeder::class);
});

function bearerFor(User $user): string
{
    return $user->createToken('test-suite')->plainTextToken;
}

it('records a valid vote on a published article, updates the summary, writes an audit log, and dispatches events', function (): void {
    Event::fake([VoteRecorded::class, VotingResultsUpdated::class]);

    $participant = identityUser('participant');
    $period = VotingPeriod::factory()->create();
    $article = Article::factory()->withStatus(ArticleStatus::Published)->create();

    $response = $this->withToken(bearerFor($participant))
        ->postJson('/api/v1/articles/'.$article->getKey().'/votes');

    $response->assertCreated()
        ->assertJsonPath('data.article_id', $article->getKey())
        ->assertJsonPath('data.vote_count', 1)
        ->assertJsonPath('data.voting_period_id', $period->getKey());

    $this->assertDatabaseHas('votes', [
        'user_id' => $participant->getKey(),
        'article_id' => $article->getKey(),
        'voting_period_id' => $period->getKey(),
    ]);

    $this->assertDatabaseHas('article_vote_summaries', [
        'article_id' => $article->getKey(),
        'vote_count' => 1,
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $participant->getKey(),
        'action' => 'vote.cast',
    ]);

    Event::assertDispatched(VoteRecorded::class, fn (VoteRecorded $event): bool => $event->vote->article_id === $article->getKey()
        && $event->voteCount === 1);

    Event::assertDispatched(VotingResultsUpdated::class, fn (VotingResultsUpdated $event): bool => $event->periodId === $period->getKey()
        && $event->articleId === $article->getKey()
        && $event->voteCount === 1);
});

it('allows a participant to vote on multiple different articles including by slug', function (): void {
    $participant = identityUser('participant');
    VotingPeriod::factory()->create();

    $first = Article::factory()->withStatus(ArticleStatus::Published)->create();
    $second = Article::factory()->withStatus(ArticleStatus::Published)->create();

    $token = bearerFor($participant);

    $this->withToken($token)
        ->postJson('/api/v1/articles/'.$first->getKey().'/votes')
        ->assertCreated()
        ->assertJsonPath('data.vote_count', 1);

    $this->withToken($token)
        ->postJson('/api/v1/articles/'.$second->slug.'/votes')
        ->assertCreated()
        ->assertJsonPath('data.vote_count', 1);

    expect(Vote::query()->where('user_id', $participant->getKey())->count())->toBe(2);
});

it('rejects a duplicate vote by the same participant on the same article with 409 Conflict', function (): void {
    $participant = identityUser('participant');
    $period = VotingPeriod::factory()->create();
    $article = Article::factory()->withStatus(ArticleStatus::Published)->create();
    $token = bearerFor($participant);

    $this->withToken($token)
        ->postJson('/api/v1/articles/'.$article->getKey().'/votes')
        ->assertCreated();

    $this->withToken($token)
        ->postJson('/api/v1/articles/'.$article->getKey().'/votes')
        ->assertStatus(409)
        ->assertJsonPath('code', 'vote_already_cast');

    expect(Vote::query()
        ->where('user_id', $participant->getKey())
        ->where('article_id', $article->getKey())
        ->count())->toBe(1);

    expect(ArticleVoteSummary::query()->findOrFail($article->getKey())->vote_count)->toBe(1);
    expect($period->votes()->count())->toBe(1);
});

it('enforces uniqueness at the database constraint layer under concurrent-like collisions', function (): void {
    $participant = identityUser('participant');
    $period = VotingPeriod::factory()->create();
    $article = Article::factory()->withStatus(ArticleStatus::Published)->create();

    // Simulate a winning concurrent transaction writing directly before the second hits insertVote().
    Vote::query()->create([
        'user_id' => $participant->getKey(),
        'article_id' => $article->getKey(),
        'voting_period_id' => $period->getKey(),
        'created_at' => now(),
    ]);
    ArticleVoteSummary::query()->create([
        'article_id' => $article->getKey(),
        'vote_count' => 1,
        'updated_at' => now(),
    ]);

    expect(fn () => app(VotingTransactionService::class)->castVote($participant, $article->getKey()))
        ->toThrow(VotingException::class);

    expect(Vote::query()->where('article_id', $article->getKey())->count())->toBe(1);
    expect(ArticleVoteSummary::query()->findOrFail($article->getKey())->vote_count)->toBe(1);
});

it('rejects votes on non-published articles', function (ArticleStatus $status): void {
    $participant = identityUser('participant');
    VotingPeriod::factory()->create();
    $article = Article::factory()->withStatus($status)->create();

    $this->withToken(bearerFor($participant))
        ->postJson('/api/v1/articles/'.$article->getKey().'/votes')
        ->assertNotFound()
        ->assertJsonPath('code', 'article_not_eligible');
})->with([
    'draft' => ArticleStatus::Draft,
    'unpublished' => ArticleStatus::Unpublished,
]);

it('rate limits vote casting after 5 attempts per minute', function (): void {
    $participant = identityUser('participant');
    VotingPeriod::factory()->create();
    $token = bearerFor($participant);

    for ($i = 0; $i < 5; $i++) {
        $article = Article::factory()->withStatus(ArticleStatus::Published)->create();
        $this->withToken($token)
            ->postJson('/api/v1/articles/'.$article->getKey().'/votes')
            ->assertCreated();
    }

    $sixth = Article::factory()->withStatus(ArticleStatus::Published)->create();
    $this->withToken($token)
        ->postJson('/api/v1/articles/'.$sixth->getKey().'/votes')
        ->assertStatus(429)
        ->assertJsonPath('code', 'rate_limited');
});
