<?php

declare(strict_types=1);

namespace App\Modules\Articles\Models;

use App\Modules\Articles\Database\Factories\ArticleFactory;
use App\Modules\Articles\Enums\ArticleStatus;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'author_id',
        'category_id',
        'title',
        'slug',
        'abstract',
        'body',
        'status',
        'is_featured',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ArticleStatus::class,
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Public catalogue visibility: published and already public.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', ArticleStatus::Published->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';

        return $query->where(function (Builder $inner) use ($like): void {
            $inner->where('title', 'like', $like)
                ->orWhere('abstract', 'like', $like);
        });
    }

    public function scopeCategory(Builder $query, ?string $categorySlug): Builder
    {
        if ($categorySlug === null || $categorySlug === '') {
            return $query;
        }

        return $query->whereHas('category', fn (Builder $inner) => $inner->where('slug', $categorySlug));
    }

    /**
     * Sorting allow-list. Client values never reach the query directly.
     *
     * "popular" is ordered by featured-then-date until the Voting module
     * exposes vote summaries; Articles intentionally does not join another
     * module's tables for it.
     */
    public function scopeSort(Builder $query, ?string $sort): Builder
    {
        return match ($sort) {
            'popular' => $query->orderByDesc('is_featured')->orderByDesc('published_at'),
            default => $query->orderByDesc('published_at'),
        };
    }

    public function isPublic(): bool
    {
        return $this->status->isPublic() && $this->published_at !== null;
    }

    public function isEditableByOwner(): bool
    {
        return $this->status->isEditableByOwner();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ArticleCategory::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ArticleAttachment::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ArticleStatusHistory::class);
    }

    protected static function newFactory(): ArticleFactory
    {
        return ArticleFactory::new();
    }
}
