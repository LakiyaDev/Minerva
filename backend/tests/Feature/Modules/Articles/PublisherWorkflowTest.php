<?php

declare(strict_types=1);

use App\Modules\Articles\Database\Seeders\ArticleCategorySeeder;
use App\Modules\Articles\Enums\ArticleStatus;
use App\Modules\Articles\Models\Article;
use App\Modules\Articles\Models\ArticleCategory;
use App\Modules\Articles\Models\ArticleStatusHistory;
use App\Modules\Identity\Services\PublisherPermissionService;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    seedIdentity();
    $this->seed(ArticleCategorySeeder::class);
});

function publisherPayload(?ArticleCategory $category = null): array
{
    $category ??= ArticleCategory::query()->where('slug', 'ai-in-medicine')->firstOrFail();

    return [
        'title' => 'Explainable triage for district hospitals',
        'abstract' => 'A short summary of the evaluation.',
        'body' => 'The full article body.',
        'category_id' => $category->getKey(),
    ];
}

it('creates a draft article for a publisher', function (): void {
    $publisher = identityUser('publisher');

    Sanctum::actingAs($publisher);

    $this->postJson('/api/v1/publisher/articles', publisherPayload())
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.title', 'Explainable triage for district hospitals')
        ->assertJsonPath('data.slug', 'explainable-triage-for-district-hospitals');

    $article = Article::query()->firstOrFail();

    expect($article->author_id)->toBe($publisher->getKey())
        ->and($article->status)->toBe(ArticleStatus::Draft)
        ->and($article->published_at)->toBeNull()
        ->and(ArticleStatusHistory::query()->where('article_id', $article->getKey())->count())->toBe(1);
});

it('rejects a draft creation without the create permission', function (): void {
    $participant = identityUser('participant');

    Sanctum::actingAs($participant);

    $this->postJson('/api/v1/publisher/articles', publisherPayload())->assertForbidden();
});

it('validates the draft payload', function (): void {
    $publisher = identityUser('publisher');

    Sanctum::actingAs($publisher);

    $this->postJson('/api/v1/publisher/articles', [
        'title' => '',
        'abstract' => '',
        'body' => '',
        'category_id' => 'not-a-ulid',
    ])->assertStatus(422)->assertJsonValidationErrors(['title', 'abstract', 'body', 'category_id']);
});

it('prevents a publisher from editing another author article', function (): void {
    $publisher = identityUser('publisher');
    $other = identityUser('publisher');

    $article = Article::factory()->withStatus(ArticleStatus::Draft)->create([
        'author_id' => $other->getKey(),
    ]);

    Sanctum::actingAs($publisher);

    $this->patchJson('/api/v1/publisher/articles/'.$article->getKey(), [
        'title' => 'Hijacked title',
    ])->assertForbidden();

    $this->getJson('/api/v1/publisher/articles/'.$article->getKey())->assertForbidden();

    expect($article->fresh()->title)->not->toBe('Hijacked title');
});

it('allows a publisher to edit their own draft', function (): void {
    $publisher = identityUser('publisher');

    $article = Article::factory()->withStatus(ArticleStatus::Draft)->create([
        'author_id' => $publisher->getKey(),
    ]);

    Sanctum::actingAs($publisher);

    $this->patchJson('/api/v1/publisher/articles/'.$article->getKey(), [
        'abstract' => 'A revised abstract.',
    ])
        ->assertOk()
        ->assertJsonPath('data.abstract', 'A revised abstract.');

    expect($article->fresh()->abstract)->toBe('A revised abstract.');
});

it('submits a draft for review', function (): void {
    $publisher = identityUser('publisher');

    $article = Article::factory()->withStatus(ArticleStatus::Draft)->create([
        'author_id' => $publisher->getKey(),
    ]);

    Sanctum::actingAs($publisher);

    $this->postJson('/api/v1/publisher/articles/'.$article->getKey().'/submit')
        ->assertOk()
        ->assertJsonPath('data.status', 'pending_review');

    expect($article->fresh()->status)->toBe(ArticleStatus::PendingReview);
});

it('refuses to submit an article that is already pending review', function (): void {
    $publisher = identityUser('publisher');

    $article = Article::factory()->withStatus(ArticleStatus::PendingReview)->create([
        'author_id' => $publisher->getKey(),
    ]);

    Sanctum::actingAs($publisher);

    $this->postJson('/api/v1/publisher/articles/'.$article->getKey().'/submit')->assertForbidden();
});

it('blocks direct publishing without an active direct-publish grant', function (): void {
    $publisher = identityUser('publisher');

    $article = Article::factory()->withStatus(ArticleStatus::Draft)->create([
        'author_id' => $publisher->getKey(),
    ]);

    Sanctum::actingAs($publisher);

    $this->postJson('/api/v1/publisher/articles/'.$article->getKey().'/publish')->assertForbidden();

    expect($article->fresh()->status)->toBe(ArticleStatus::Draft);
});

it('allows direct publishing while a direct-publish grant is active', function (): void {
    $admin = identityUser('administrator');
    $publisher = identityUser('publisher');

    app(PublisherPermissionService::class)->grant(
        $publisher,
        true,
        $admin,
        Request::create('/api/v1/admin/users/'.$publisher->getKey().'/publisher-permission', 'POST'),
    );

    $article = Article::factory()->withStatus(ArticleStatus::Draft)->create([
        'author_id' => $publisher->getKey(),
    ]);

    Sanctum::actingAs($publisher->fresh());

    $this->postJson('/api/v1/publisher/articles/'.$article->getKey().'/publish')
        ->assertOk()
        ->assertJsonPath('data.status', 'published');

    expect($article->fresh()->status)->toBe(ArticleStatus::Published)
        ->and($article->fresh()->published_at)->not->toBeNull();
});

it('revokes direct publishing when the grant is switched off', function (): void {
    $admin = identityUser('administrator');
    $publisher = identityUser('publisher');
    $request = Request::create('/api/v1/admin/users/'.$publisher->getKey().'/publisher-permission', 'POST');

    $service = app(PublisherPermissionService::class);
    $service->grant($publisher, true, $admin, $request);
    $service->updateDirectPublish($publisher->fresh(), false, $admin, $request);

    $article = Article::factory()->withStatus(ArticleStatus::Draft)->create([
        'author_id' => $publisher->getKey(),
    ]);

    Sanctum::actingAs($publisher->fresh());

    $this->postJson('/api/v1/publisher/articles/'.$article->getKey().'/publish')->assertForbidden();
});

it('never exposes another publisher article in the workspace list', function (): void {
    $publisher = identityUser('publisher');
    $other = identityUser('publisher');

    Article::factory()->withStatus(ArticleStatus::Published)->create([
        'author_id' => $other->getKey(),
        'title' => 'Another publisher private article',
    ]);

    Sanctum::actingAs($publisher);

    $this->getJson('/api/v1/publisher/articles')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
