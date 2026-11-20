<?php

declare(strict_types=1);

use App\Modules\Articles\Database\Seeders\ArticleCategorySeeder;
use App\Modules\Articles\Enums\ArticleStatus;
use App\Modules\Articles\Models\Article;
use App\Modules\Identity\Models\User;
use App\Modules\Voting\Models\VotingPeriod;
use Illuminate\Support\Facades\Auth;

beforeEach(function (): void {
    seedIdentity();
    $this->seed(ArticleCategorySeeder::class);
});

function withSanctumToken(User $user): string
{
    Auth::forgetGuards();

    return $user->createToken('test')->plainTextToken;
}

it('blocks unauthenticated guests from voting, viewing live results, leaderboard, and my-votes', function (): void {
    VotingPeriod::factory()->create();
    $article = Article::factory()->withStatus(ArticleStatus::Published)->create();

    $this->postJson('/api/v1/articles/'.$article->getKey().'/votes')->assertUnauthorized();
    $this->getJson('/api/v1/articles/'.$article->getKey().'/vote-results')->assertUnauthorized();
    $this->getJson('/api/v1/leaderboard')->assertUnauthorized();
    $this->getJson('/api/v1/me/votes')->assertUnauthorized();
});

it('forbids users without participant voting permissions and deactivated accounts', function (): void {
    VotingPeriod::factory()->create();
    $article = Article::factory()->withStatus(ArticleStatus::Published)->create();

    $publisherOnly = identityUser('publisher');
    $publisherToken = $publisherOnly->createToken('test')->plainTextToken;

    $this->withToken($publisherToken)
        ->postJson('/api/v1/articles/'.$article->getKey().'/votes')
        ->assertForbidden();

    $this->withToken($publisherToken)
        ->getJson('/api/v1/articles/'.$article->getKey().'/vote-results')
        ->assertForbidden();

    $this->withToken($publisherToken)
        ->getJson('/api/v1/leaderboard')
        ->assertForbidden();

    $inactiveParticipant = identityUser('participant', ['is_active' => false]);
    $this->withToken(withSanctumToken($inactiveParticipant))
        ->postJson('/api/v1/articles/'.$article->getKey().'/votes')
        ->assertForbidden();
});

it('returns accurate live article results, ranked leaderboard, and isolated participant vote history', function (): void {
    VotingPeriod::factory()->create();

    $leader = Article::factory()->withStatus(ArticleStatus::Published)->create(['title' => 'Leading Essay']);
    $runnerUp = Article::factory()->withStatus(ArticleStatus::Published)->create(['title' => 'Runner Up Essay']);
    $draft = Article::factory()->withStatus(ArticleStatus::Draft)->create(['title' => 'Hidden Draft']);

    $alice = identityUser('participant');
    $bob = identityUser('participant');

    $aliceToken = $alice->createToken('test')->plainTextToken;
    $bobToken = $bob->createToken('test')->plainTextToken;

    Auth::forgetGuards();
    $this->withToken($aliceToken)->postJson('/api/v1/articles/'.$leader->getKey().'/votes')->assertCreated();
    Auth::forgetGuards();
    $this->withToken($bobToken)->postJson('/api/v1/articles/'.$leader->getKey().'/votes')->assertCreated();
    $this->withToken($bobToken)->postJson('/api/v1/articles/'.$runnerUp->getKey().'/votes')->assertCreated();

    // Article results for Alice (voted on leader, not runnerUp)
    Auth::forgetGuards();
    $this->withToken($aliceToken)
        ->getJson('/api/v1/articles/'.$leader->slug.'/vote-results')
        ->assertOk()
        ->assertJsonPath('data.vote_count', 2)
        ->assertJsonPath('data.rank', 1)
        ->assertJsonPath('data.has_voted', true)
        ->assertJsonPath('data.voting_status', 'open');

    $this->withToken($aliceToken)
        ->getJson('/api/v1/articles/'.$runnerUp->getKey().'/vote-results')
        ->assertOk()
        ->assertJsonPath('data.vote_count', 1)
        ->assertJsonPath('data.rank', 2)
        ->assertJsonPath('data.has_voted', false);

    // Draft article vote results return 404
    $this->withToken($aliceToken)
        ->getJson('/api/v1/articles/'.$draft->getKey().'/vote-results')
        ->assertNotFound();

    // Leaderboard reflects exact ordering and excludes drafts
    $this->withToken($aliceToken)
        ->getJson('/api/v1/leaderboard')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.article_id', $leader->getKey())
        ->assertJsonPath('data.0.rank', 1)
        ->assertJsonPath('data.0.vote_count', 2)
        ->assertJsonPath('data.0.has_voted', true)
        ->assertJsonPath('data.1.article_id', $runnerUp->getKey())
        ->assertJsonPath('data.1.rank', 2)
        ->assertJsonPath('data.1.vote_count', 1)
        ->assertJsonPath('data.1.has_voted', false)
        ->assertJsonPath('meta.total_votes', 3);

    // My votes isolates Alice's history (1 vote) from Bob's (2 votes)
    $this->withToken($aliceToken)
        ->getJson('/api/v1/me/votes')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.article_id', $leader->getKey())
        ->assertJsonPath('meta.total', 1);

    Auth::forgetGuards();
    $this->withToken($bobToken)
        ->getJson('/api/v1/me/votes')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 2);
});
