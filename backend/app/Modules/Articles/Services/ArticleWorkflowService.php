<?php

declare(strict_types=1);

namespace App\Modules\Articles\Services;

use App\Modules\Articles\Enums\ArticleStatus;
use App\Modules\Articles\Events\ArticlePublished;
use App\Modules\Articles\Events\ArticleStatusChanged;
use App\Modules\Articles\Events\ArticleUnpublished;
use App\Modules\Articles\Exceptions\ArticleException;
use App\Modules\Articles\Models\Article;
use App\Modules\Articles\Models\ArticleStatusHistory;
use App\Modules\Identity\Models\User;
use App\Shared\Contracts\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Single owner of article business writes: creation, editorial transitions
 * and the featured flag. Controllers stay thin and other modules integrate
 * through this service or the published domain events.
 */
final class ArticleWorkflowService
{
    /**
     * Allowed editorial state machine. Draft -> Draft represents a publisher
     * editing an existing draft.
     */
    private const TRANSITIONS = [
        'draft' => ['draft', 'published'],
        'published' => ['unpublished'],
        'unpublished' => ['published'],
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{title: string, abstract: string, body: string, category_id: string}  $attributes
     */
    public function createDraft(User $author, array $attributes): Article
    {
        $article = new Article([
            'title' => $attributes['title'],
            'abstract' => $attributes['abstract'],
            'body' => $attributes['body'],
            'category_id' => $attributes['category_id'],
            'author_id' => $author->getKey(),
            'status' => ArticleStatus::Draft,
        ]);

        $article = DB::transaction(function () use ($article, $author): Article {
            $article->slug = $this->generateSlug($article->title);
            $article->save();
            $this->recordHistory($article, null, ArticleStatus::Draft, $author, null);
            $this->audit->record('article.created', $author, $article, ['title' => $article->title], $this->request());

            return $article;
        });

        return $article->refresh();
    }

    /**
     * @param  array{title?: string, abstract?: string, body?: string, category_id?: string}  $attributes
     */
    public function updateDraft(User $actor, Article $article, array $attributes): Article
    {
        if (! $article->isEditableByOwner()) {
            throw ArticleException::notEditable($article->status);
        }

        $titleChanged = isset($attributes['title']) && $attributes['title'] !== $article->title;

        $updated = DB::transaction(function () use ($article, $actor, $attributes, $titleChanged): Article {
            $article->fill($attributes);

            if ($titleChanged) {
                // The slug is derived from the title and re-deriving keeps it
                // current. Uniqueness is re-checked so nothing can collide.
                $article->slug = $this->generateSlug((string) $article->title);
            }

            $article->save();
            $this->audit->record(
                'article.updated',
                $actor,
                $article,
                ['status' => $article->status->value, 'slug_changed' => $titleChanged],
                $this->request(),
            );

            return $article;
        });

        return $updated->refresh();
    }

    public function transition(
        Article $article,
        ArticleStatus $to,
        ?User $actor,
        ?string $reason = null,
    ): Article {
        $from = $article->status;

        if (! $this->allowed($from, $to)) {
            throw ArticleException::invalidTransition($from, $to);
        }

        $updated = DB::transaction(function () use ($article, $from, $to, $actor, $reason): Article {
            $article->status = $to;

            if ($to->isPublished()) {
                $article->published_at = now();
            }

            $article->save();
            $this->recordHistory($article, $from, $to, $actor, $reason);
            $this->audit->record(
                'article.status.'.$to->value,
                $actor,
                $article,
                [
                    'from' => $from->value,
                    'to' => $to->value,
                    'reason' => $reason,
                    'slug' => $article->slug,
                ],
                $this->request(),
            );

            return $article;
        });

        // dispatched after the transaction commits
        $this->dispatchEvents($updated->refresh(), $from, $to, $actor, $reason);

        return $updated->refresh();
    }

    public function setFeatured(User $actor, Article $article, bool $featured): Article
    {
        $updated = DB::transaction(function () use ($article, $actor, $featured): Article {
            $article->is_featured = $featured;
            $article->save();
            $this->audit->record(
                'article.featured',
                $actor,
                $article,
                ['is_featured' => $featured],
                $this->request(),
            );

            return $article;
        });

        return $updated->refresh();
    }

    private function allowed(ArticleStatus $from, ArticleStatus $to): bool
    {
        return in_array($to->value, self::TRANSITIONS[$from->value] ?? [], true);
    }

    private function recordHistory(
        Article $article,
        ?ArticleStatus $from,
        ArticleStatus $to,
        ?User $actor,
        ?string $reason,
    ): ArticleStatusHistory {
        return ArticleStatusHistory::query()->create([
            'article_id' => $article->getKey(),
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'acted_by' => $actor?->getKey(),
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }

    private function dispatchEvents(
        Article $article,
        ?ArticleStatus $from,
        ArticleStatus $to,
        ?User $actor,
        ?string $reason,
    ): void {
        $changed = new ArticleStatusChanged($article, $from, $to, $actor, $reason);

        event($changed);

        if ($to->isPublished()) {
            event(new ArticlePublished($article, $from, $to, $actor, $reason));
        }

        if ($to->isUnpublished()) {
            event(new ArticleUnpublished($article, $from, $to, $actor, $reason));
        }
    }

    private function generateSlug(string $title): string
    {
        $base = Str::slug($title);

        if ($base === '') {
            $base = 'article';
        }

        $slug = $base;
        $suffix = 2;

        // Articles are never hard-deleted, so the slug is unique for the whole
        // lifetime of the table and the plain lookup is enough.
        while (Article::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function request(): ?Request
    {
        return app()->bound(Request::class) ? app(Request::class) : null;
    }
}
