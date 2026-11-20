<?php

declare(strict_types=1);

use App\Modules\Articles\Database\Seeders\ArticleCategorySeeder;
use App\Modules\Articles\Enums\ArticleStatus;
use App\Modules\Articles\Models\Article;
use App\Modules\Articles\Models\ArticleCategory;
use App\Modules\Comments\Enums\CommentStatus;
use App\Modules\Comments\Models\Comment;
use App\Modules\Identity\Models\User;
use App\Modules\Voting\Models\Vote;
use App\Modules\Voting\Models\VotingPeriod;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    seedIdentity();
    $this->seed(ArticleCategorySeeder::class);
});

function actingAsAnalyticsAdmin(): User
{
    $admin = identityUser('administrator');
    enableTotp($admin);

    Sanctum::actingAs($admin->fresh());

    return $admin;
}

it('returns comprehensive platform-wide analytics for an MFA-verified administrator (FR-53)', function (): void {
    $category = ArticleCategory::firstOrFail();
    $author = identityUser('publisher');
    $voter = identityUser('participant');

    $pubArticle = Article::factory()->withStatus(ArticleStatus::Published)->create([
        'author_id' => $author->getKey(),
        'category_id' => $category->getKey(),
        'is_featured' => true,
    ]);
    Article::factory()->withStatus(ArticleStatus::Draft)->create([
        'author_id' => $author->getKey(),
    ]);

    $period = VotingPeriod::factory()->create();
    Vote::create([
        'user_id' => $voter->getKey(),
        'article_id' => $pubArticle->getKey(),
        'voting_period_id' => $period->getKey(),
    ]);

    Comment::factory()->create([
        'article_id' => $pubArticle->getKey(),
        'user_id' => $voter->getKey(),
        'status' => CommentStatus::Visible,
    ]);
    Comment::factory()->create([
        'article_id' => $pubArticle->getKey(),
        'user_id' => $voter->getKey(),
        'status' => CommentStatus::Removed,
    ]);

    actingAsAnalyticsAdmin();

    $response = $this->getJson('/api/v1/admin/analytics');

    $response->assertOk()
        ->assertJsonPath('data.articles_summary.total', 2)
        ->assertJsonPath('data.articles_summary.by_status.published', 1)
        ->assertJsonPath('data.articles_summary.by_status.draft', 1)
        ->assertJsonPath('data.articles_summary.featured_count', 1)
        ->assertJsonPath('data.voting.total_votes', 1)
        ->assertJsonPath('data.voting.unique_voters', 1)
        ->assertJsonPath('data.community.total_comments', 2)
        ->assertJsonPath('data.community.visible_comments', 1)
        ->assertJsonPath('data.community.removed_comments', 1);

    expect($response->json('data.community.moderation_rate_percent'))->toEqual(50.0)
        ->and(count($response->json('data.top_performing_articles')))->toBe(1)
        ->and(count($response->json('data.voting.daily_trend')))->toBe(14);
});

it('blocks non-staff users from accessing platform-wide analytics', function (): void {
    $publisher = identityUser('publisher');

    Sanctum::actingAs($publisher);

    $this->getJson('/api/v1/admin/analytics')->assertForbidden();
});

it('requires MFA enrolment before staff can access platform-wide analytics', function (): void {
    $admin = identityUser('administrator');

    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/analytics')->assertForbidden();
});
