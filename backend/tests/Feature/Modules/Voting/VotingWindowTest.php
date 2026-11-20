<?php

declare(strict_types=1);

use App\Modules\Articles\Database\Seeders\ArticleCategorySeeder;
use App\Modules\Articles\Enums\ArticleStatus;
use App\Modules\Articles\Models\Article;
use App\Modules\Voting\Models\VotingPeriod;
use Carbon\Carbon;

beforeEach(function (): void {
    seedIdentity();
    $this->seed(ArticleCategorySeeder::class);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('reports closed status and rejects votes when no active voting period exists', function (): void {
    $participant = identityUser('participant');
    $article = Article::factory()->withStatus(ArticleStatus::Published)->create();

    $this->getJson('/api/v1/voting/status')
        ->assertOk()
        ->assertJsonPath('data.status', 'closed')
        ->assertJsonPath('data.is_open', false)
        ->assertJsonPath('data.period', null);

    $this->withToken($participant->createToken('test')->plainTextToken)
        ->postJson('/api/v1/articles/'.$article->getKey().'/votes')
        ->assertStatus(422)
        ->assertJsonPath('code', 'voting_period_inactive');
});

it('enforces exact voting period start and end boundaries using server time', function (): void {
    $anchor = Carbon::create(2026, 12, 1, 12, 0, 0, 'UTC');
    Carbon::setTestNow($anchor);

    $admin = identityUser('administrator');
    $period = VotingPeriod::factory()->create([
        'starts_at' => $anchor->copy()->addHours(2),
        'ends_at' => $anchor->copy()->addHours(6),
        'is_active' => true,
        'created_by' => $admin->getKey(),
    ]);

    $participant = identityUser('participant');
    $token = $participant->createToken('test')->plainTextToken;
    $article = Article::factory()->withStatus(ArticleStatus::Published)->create();

    // 1. Before starts_at => upcoming
    $this->getJson('/api/v1/voting/status')
        ->assertOk()
        ->assertJsonPath('data.status', 'upcoming')
        ->assertJsonPath('data.is_open', false)
        ->assertJsonPath('data.period.id', $period->getKey());

    $this->withToken($token)
        ->postJson('/api/v1/articles/'.$article->getKey().'/votes')
        ->assertStatus(422)
        ->assertJsonPath('code', 'voting_period_inactive');

    // 2. Inside window [starts_at, ends_at] => open
    Carbon::setTestNow($anchor->copy()->addHours(3));

    $this->getJson('/api/v1/voting/status')
        ->assertOk()
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.is_open', true);

    $this->withToken($token)
        ->postJson('/api/v1/articles/'.$article->getKey().'/votes')
        ->assertCreated();

    // 3. Past ends_at => closed
    Carbon::setTestNow($anchor->copy()->addHours(7));

    $otherArticle = Article::factory()->withStatus(ArticleStatus::Published)->create();

    $this->getJson('/api/v1/voting/status')
        ->assertOk()
        ->assertJsonPath('data.status', 'closed')
        ->assertJsonPath('data.is_open', false);

    $this->withToken($token)
        ->postJson('/api/v1/articles/'.$otherArticle->getKey().'/votes')
        ->assertStatus(422)
        ->assertJsonPath('code', 'voting_period_inactive');
});

it('rejects votes when the configured voting period is inactive', function (): void {
    VotingPeriod::factory()->inactive()->create();

    $participant = identityUser('participant');
    $article = Article::factory()->withStatus(ArticleStatus::Published)->create();

    $this->withToken($participant->createToken('test')->plainTextToken)
        ->postJson('/api/v1/articles/'.$article->getKey().'/votes')
        ->assertStatus(422)
        ->assertJsonPath('code', 'voting_period_inactive');
});
