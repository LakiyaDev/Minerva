<?php

declare(strict_types=1);

use App\Modules\Articles\Database\Seeders\ArticleCategorySeeder;
use App\Modules\Articles\Enums\ArticleStatus;
use App\Modules\Articles\Models\Article;
use App\Modules\Articles\Models\ArticleStatusHistory;
use App\Modules\Identity\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    seedIdentity();
    $this->seed(ArticleCategorySeeder::class);
});

/**
 * Administrators are staff, so the MFA middleware requires a confirmed TOTP
 * factor before any editorial route is reachable.
 */
function actingAsAdmin(): User
{
    $admin = identityUser('administrator');
    enableTotp($admin);

    Sanctum::actingAs($admin->fresh());

    return $admin;
}

it('shows the review queue to an administrator', function (): void {
    actingAsAdmin();

    $pending = Article::factory()->withStatus(ArticleStatus::PendingReview)->create();
    Article::factory()->withStatus(ArticleStatus::Draft)->create();
    Article::factory()->withStatus(ArticleStatus::Published)->create();

    $this->getJson('/api/v1/admin/articles/review-queue')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $pending->getKey())
        ->assertJsonPath('data.0.status', 'pending_review');
});

it('lists every status for administrators with filters', function (): void {
    actingAsAdmin();

    Article::factory()->withStatus(ArticleStatus::Published)->create(['title' => 'Published piece']);
    Article::factory()->withStatus(ArticleStatus::Draft)->create(['title' => 'Draft piece']);

    $this->getJson('/api/v1/admin/articles?status=draft')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'draft');

    $this->getJson('/api/v1/admin/articles?search=Published')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('approves a pending article', function (): void {
    actingAsAdmin();

    $article = Article::factory()->withStatus(ArticleStatus::PendingReview)->create();

    $this->postJson('/api/v1/admin/articles/'.$article->getKey().'/approve')
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    expect($article->fresh()->status)->toBe(ArticleStatus::Approved);
});

it('rejects a pending article and stores the reason in the history', function (): void {
    actingAsAdmin();

    $article = Article::factory()->withStatus(ArticleStatus::PendingReview)->create();

    $this->postJson('/api/v1/admin/articles/'.$article->getKey().'/reject', [
        'reason' => 'The methodology section needs supporting citations.',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    $history = ArticleStatusHistory::query()
        ->where('article_id', $article->getKey())
        ->where('to_status', ArticleStatus::Rejected->value)
        ->firstOrFail();

    expect($history->reason)->toBe('The methodology section needs supporting citations.')
        ->and($history->from_status)->toBe(ArticleStatus::PendingReview->value);
});

it('requires a rejection reason', function (): void {
    actingAsAdmin();

    $article = Article::factory()->withStatus(ArticleStatus::PendingReview)->create();

    $this->postJson('/api/v1/admin/articles/'.$article->getKey().'/reject', [
        'reason' => 'no',
    ])->assertStatus(422)->assertJsonValidationErrors(['reason']);
});

it('publishes and unpublishes an article', function (): void {
    actingAsAdmin();

    $article = Article::factory()->withStatus(ArticleStatus::Approved)->create();

    $this->postJson('/api/v1/admin/articles/'.$article->getKey().'/publish')
        ->assertOk()
        ->assertJsonPath('data.status', 'published');

    expect($article->fresh()->published_at)->not->toBeNull();

    $this->postJson('/api/v1/admin/articles/'.$article->getKey().'/unpublish')
        ->assertOk()
        ->assertJsonPath('data.status', 'unpublished');

    $this->getJson('/api/v1/articles/'.$article->fresh()->slug)->assertNotFound();
});

it('republishes an unpublished article', function (): void {
    actingAsAdmin();

    $article = Article::factory()->withStatus(ArticleStatus::Unpublished)->create();

    $this->postJson('/api/v1/admin/articles/'.$article->getKey().'/publish')
        ->assertOk()
        ->assertJsonPath('data.status', 'published');
});

it('toggles the featured flag', function (): void {
    actingAsAdmin();

    $article = Article::factory()->withStatus(ArticleStatus::Published)->create();

    $this->postJson('/api/v1/admin/articles/'.$article->getKey().'/feature', [
        'is_featured' => true,
    ])
        ->assertOk()
        ->assertJsonPath('data.is_featured', true);

    $this->postJson('/api/v1/admin/articles/'.$article->getKey().'/feature', [
        'is_featured' => false,
    ])
        ->assertOk()
        ->assertJsonPath('data.is_featured', false);
});

it('blocks publishers from the editorial routes', function (): void {
    $publisher = identityUser('publisher');
    enableTotp($publisher);

    Sanctum::actingAs($publisher->fresh());

    $this->getJson('/api/v1/admin/articles/review-queue')->assertForbidden();
});

it('blocks participants from the editorial routes', function (): void {
    $participant = identityUser('participant');

    Sanctum::actingAs($participant);

    $this->getJson('/api/v1/admin/articles/review-queue')->assertForbidden();
});

it('requires MFA enrolment before editorial routes are reachable', function (): void {
    $admin = identityUser('administrator');

    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/articles/review-queue')
        ->assertForbidden()
        ->assertJsonPath('code', 'mfa_enrollment_required');
});
