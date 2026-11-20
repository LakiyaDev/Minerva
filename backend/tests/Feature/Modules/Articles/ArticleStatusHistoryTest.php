<?php

declare(strict_types=1);

use App\Modules\Articles\Database\Seeders\ArticleCategorySeeder;
use App\Modules\Articles\Enums\ArticleStatus;
use App\Modules\Articles\Events\ArticlePublished;
use App\Modules\Articles\Events\ArticleStatusChanged;
use App\Modules\Articles\Events\ArticleUnpublished;
use App\Modules\Articles\Exceptions\ArticleException;
use App\Modules\Articles\Models\Article;
use App\Modules\Articles\Models\ArticleStatusHistory;
use App\Modules\Articles\Services\ArticleWorkflowService;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    seedIdentity();
    $this->seed(ArticleCategorySeeder::class);
});

it('records an append-only history entry for every transition', function (): void {
    Event::fake([ArticleStatusChanged::class, ArticlePublished::class, ArticleUnpublished::class]);

    $author = identityUser('publisher');
    $workflow = app(ArticleWorkflowService::class);

    $article = $workflow->createDraft($author, [
        'title' => 'Workflow evidence',
        'abstract' => 'Abstract',
        'body' => 'Body',
        'category_id' => Article::factory()->create()->category_id,
    ]);

    expect(ArticleStatusHistory::query()->count())->toBe(1);

    $creation = ArticleStatusHistory::query()->firstOrFail();
    expect($creation->from_status)->toBeNull()
        ->and($creation->to_status)->toBe(ArticleStatus::Draft->value)
        ->and($creation->acted_by)->toBe($author->getKey());

    $workflow->transition($article, ArticleStatus::Published, $author);
    $workflow->transition($article, ArticleStatus::Unpublished, $author);

    $history = ArticleStatusHistory::query()
        ->where('article_id', $article->getKey())
        ->orderBy('created_at')
        ->orderBy('id')
        ->get();

    expect($history)->toHaveCount(3)
        ->and($history->pluck('to_status')->all())->toBe([
            'draft',
            'published',
            'unpublished',
        ]);

    Event::assertDispatched(ArticlePublished::class);
    Event::assertDispatched(ArticleUnpublished::class);
});

it('rejects an illegal transition', function (): void {
    $author = identityUser('publisher');
    $workflow = app(ArticleWorkflowService::class);

    $article = Article::factory()->withStatus(ArticleStatus::Draft)->create([
        'author_id' => $author->getKey(),
    ]);

    expect(fn () => $workflow->transition($article, ArticleStatus::Unpublished, $author))
        ->toThrow(ArticleException::class);

    expect($article->fresh()->status)->toBe(ArticleStatus::Draft);
});

it('keeps the published timestamp in sync with the published status', function (): void {
    $author = identityUser('publisher');
    $workflow = app(ArticleWorkflowService::class);

    $article = Article::factory()->withStatus(ArticleStatus::Draft)->create([
        'author_id' => $author->getKey(),
    ]);

    expect($article->published_at)->toBeNull();

    $workflow->transition($article, ArticleStatus::Published, $author);

    expect($article->fresh()->published_at)->not->toBeNull();
});

it('never writes a history row when a transition is refused', function (): void {
    $author = identityUser('publisher');
    $workflow = app(ArticleWorkflowService::class);

    $article = Article::factory()->withStatus(ArticleStatus::Draft)->create([
        'author_id' => $author->getKey(),
    ]);

    $before = ArticleStatusHistory::query()->count();

    try {
        $workflow->transition($article, ArticleStatus::Unpublished, $author);
    } catch (ArticleException) {
        // expected
    }

    expect(ArticleStatusHistory::query()->count())->toBe($before);
});

it('generates a unique slug per article', function (): void {
    $author = identityUser('publisher');
    $workflow = app(ArticleWorkflowService::class);

    $first = $workflow->createDraft($author, [
        'title' => 'Duplicate title',
        'abstract' => 'Abstract',
        'body' => 'Body',
        'category_id' => Article::factory()->create()->category_id,
    ]);

    $second = $workflow->createDraft($author, [
        'title' => 'Duplicate title',
        'abstract' => 'Abstract',
        'body' => 'Body',
        'category_id' => Article::factory()->create()->category_id,
    ]);

    expect($first->slug)->toBe('duplicate-title')
        ->and($second->slug)->toBe('duplicate-title-2');
});
