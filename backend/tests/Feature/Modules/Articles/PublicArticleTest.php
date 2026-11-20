<?php

declare(strict_types=1);

use App\Modules\Articles\Database\Seeders\ArticleCategorySeeder;
use App\Modules\Articles\Enums\ArticleStatus;
use App\Modules\Articles\Models\Article;
use App\Modules\Articles\Models\ArticleCategory;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    seedIdentity();
    $this->seed(ArticleCategorySeeder::class);
});

it('lists only published articles in the public catalogue', function (): void {
    $published = Article::factory()->withStatus(ArticleStatus::Published)->create([
        'title' => 'Signal and noise in clinical AI',
    ]);

    Article::factory()->withStatus(ArticleStatus::Draft)->create();
    Article::factory()->withStatus(ArticleStatus::PendingReview)->create();
    Article::factory()->withStatus(ArticleStatus::Approved)->create();
    Article::factory()->withStatus(ArticleStatus::Rejected)->create();
    Article::factory()->withStatus(ArticleStatus::Unpublished)->create();

    $this->getJson('/api/v1/articles')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $published->getKey())
        ->assertJsonPath('data.0.status', 'published')
        ->assertJsonPath('meta.total', 1);
});

it('filters the public catalogue by category and search term', function (): void {
    $bioinformatics = ArticleCategory::query()->where('slug', 'bioinformatics')->firstOrFail();
    $sustainability = ArticleCategory::query()->where('slug', 'sustainability')->firstOrFail();

    Article::factory()->withStatus(ArticleStatus::Published)->create([
        'title' => 'Genomic pipelines for local clinics',
        'category_id' => $bioinformatics->getKey(),
    ]);

    Article::factory()->withStatus(ArticleStatus::Published)->create([
        'title' => 'Solar powered cold chains',
        'category_id' => $sustainability->getKey(),
    ]);

    $this->getJson('/api/v1/articles?category=bioinformatics')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.category.slug', 'bioinformatics');

    $this->getJson('/api/v1/articles?search=cold%20chains')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Solar powered cold chains');

    $this->getJson('/api/v1/articles?search=zzzz-no-match')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('returns a published article by slug and hides every other status', function (): void {
    $published = Article::factory()->withStatus(ArticleStatus::Published)->create([
        'title' => 'Explainable models for rural clinics',
    ]);

    $draft = Article::factory()->withStatus(ArticleStatus::Draft)->create();
    $unpublished = Article::factory()->withStatus(ArticleStatus::Unpublished)->create();

    $this->getJson('/api/v1/articles/'.$published->slug)
        ->assertOk()
        ->assertJsonPath('data.id', $published->getKey())
        ->assertJsonPath('data.title', 'Explainable models for rural clinics')
        ->assertJsonStructure(['data' => ['id', 'title', 'slug', 'abstract', 'body', 'status', 'category', 'author']]);

    $this->getJson('/api/v1/articles/'.$draft->slug)->assertNotFound();
    $this->getJson('/api/v1/articles/'.$unpublished->slug)->assertNotFound();
});

it('lists editorial categories with published article counts', function (): void {
    $category = ArticleCategory::query()->where('slug', 'ai-in-medicine')->firstOrFail();

    Article::factory()->withStatus(ArticleStatus::Published)->create([
        'category_id' => $category->getKey(),
    ]);
    Article::factory()->withStatus(ArticleStatus::Draft)->create([
        'category_id' => $category->getKey(),
    ]);

    $this->getJson('/api/v1/article-categories')
        ->assertOk()
        ->assertJsonCount(5, 'data');

    $response = $this->getJson('/api/v1/article-categories')->assertOk();

    $counts = collect($response->json('data'))->mapWithKeys(
        fn (array $entry): array => [$entry['slug'] => $entry['articles_count']]
    );

    expect($counts['ai-in-medicine'])->toBe(1)
        ->and($counts['sustainability'])->toBe(0);
});

it('keeps the workflow history private from public readers', function (): void {
    $article = Article::factory()->withStatus(ArticleStatus::Published)->create();

    $this->getJson('/api/v1/articles/'.$article->slug)
        ->assertOk()
        ->assertJsonMissingPath('data.status_history');
});

it('requires authentication for publisher article routes', function (): void {
    $this->getJson('/api/v1/publisher/articles')->assertUnauthorized();
});

it('lets an authenticated publisher see their own workspace', function (): void {
    $publisher = identityUser('publisher');
    $other = identityUser('publisher');

    $mine = Article::factory()->withStatus(ArticleStatus::Draft)->create(['author_id' => $publisher->getKey()]);
    Article::factory()->withStatus(ArticleStatus::Draft)->create(['author_id' => $other->getKey()]);

    Sanctum::actingAs($publisher);

    $this->getJson('/api/v1/publisher/articles')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->getKey());
});
