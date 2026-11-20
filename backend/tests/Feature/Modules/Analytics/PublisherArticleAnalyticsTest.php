<?php

declare(strict_types=1);

use App\Modules\Articles\Database\Seeders\ArticleCategorySeeder;
use App\Modules\Articles\Enums\ArticleStatus;
use App\Modules\Articles\Models\Article;
use App\Modules\Articles\Models\ArticleCategory;
use App\Modules\Comments\Enums\CommentStatus;
use App\Modules\Comments\Models\Comment;
use App\Modules\Voting\Models\Vote;
use App\Modules\Voting\Models\VotingPeriod;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    seedIdentity();
    $this->seed(ArticleCategorySeeder::class);
});

it('allows an author publisher to view analytics for their own published article', function (): void {
    $author = identityUser('publisher');
    $category = ArticleCategory::firstOrFail();

    $article = Article::factory()->withStatus(ArticleStatus::Published)->create([
        'author_id' => $author->getKey(),
        'category_id' => $category->getKey(),
        'published_at' => now()->subDays(5),
    ]);

    $voter1 = identityUser('participant');
    $voter2 = identityUser('publisher');
    $period = VotingPeriod::factory()->create();

    Vote::create([
        'user_id' => $voter1->getKey(),
        'article_id' => $article->getKey(),
        'voting_period_id' => $period->getKey(),
    ]);
    Vote::create([
        'user_id' => $voter2->getKey(),
        'article_id' => $article->getKey(),
        'voting_period_id' => $period->getKey(),
    ]);

    Comment::factory()->create([
        'article_id' => $article->getKey(),
        'user_id' => $voter1->getKey(),
        'status' => CommentStatus::Visible,
    ]);
    Comment::factory()->create([
        'article_id' => $article->getKey(),
        'user_id' => $voter2->getKey(),
        'status' => CommentStatus::Removed,
    ]);

    Sanctum::actingAs($author);

    $response = $this->getJson("/api/v1/publisher/articles/{$article->getKey()}/analytics");

    $response->assertOk()
        ->assertJsonPath('data.article.id', $article->getKey())
        ->assertJsonPath('data.article.title', $article->title)
        ->assertJsonPath('data.engagement.total_votes', 2)
        ->assertJsonPath('data.engagement.rank', 1)
        ->assertJsonPath('data.engagement.total_comments', 2)
        ->assertJsonPath('data.engagement.visible_comments_count', 1)
        ->assertJsonPath('data.engagement.removed_comments_count', 1);

    expect($response->json('data.benchmarks.category_average_votes'))->toEqual(2.0)
        ->and(count($response->json('data.daily_votes')))->toBe(14);
});

it('allows an author to view analytics for their draft article with zero engagement and null rank', function (): void {
    $author = identityUser('publisher');
    $article = Article::factory()->withStatus(ArticleStatus::Draft)->create([
        'author_id' => $author->getKey(),
    ]);

    Sanctum::actingAs($author);

    $this->getJson("/api/v1/publisher/articles/{$article->getKey()}/analytics")
        ->assertOk()
        ->assertJsonPath('data.engagement.total_votes', 0)
        ->assertJsonPath('data.engagement.rank', null)
        ->assertJsonPath('data.engagement.total_comments', 0);
});

it('forbids a non-owner publisher from accessing another author private article analytics (FR-52)', function (): void {
    $author = identityUser('publisher');
    $otherPublisher = identityUser('publisher');

    $article = Article::factory()->withStatus(ArticleStatus::Published)->create([
        'author_id' => $author->getKey(),
    ]);

    Sanctum::actingAs($otherPublisher);

    $this->getJson("/api/v1/publisher/articles/{$article->getKey()}/analytics")
        ->assertForbidden();
});

it('allows staff with analytics.view_all to view any publisher article analytics', function (): void {
    $author = identityUser('publisher');
    $admin = identityUser('administrator');

    $article = Article::factory()->withStatus(ArticleStatus::Published)->create([
        'author_id' => $author->getKey(),
    ]);

    Sanctum::actingAs($admin);

    $this->getJson("/api/v1/publisher/articles/{$article->getKey()}/analytics")
        ->assertOk()
        ->assertJsonPath('data.article.id', $article->getKey());
});

it('rejects unauthenticated requests to publisher article analytics', function (): void {
    $article = Article::factory()->withStatus(ArticleStatus::Published)->create();

    $this->getJson("/api/v1/publisher/articles/{$article->getKey()}/analytics")
        ->assertUnauthorized();
});
