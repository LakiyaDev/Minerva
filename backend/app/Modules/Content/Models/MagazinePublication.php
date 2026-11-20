<?php

declare(strict_types=1);

namespace App\Modules\Content\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A magazine issue — schema v1.1 §4.5.6 (FR-08, FR-09).
 *
 * The issue metadata lives here; the downloadable files live in
 * `e_magazine_files` and are served through signed URLs only.
 */
class MagazinePublication extends Model
{
    use HasUlids;

    protected $fillable = [
        'title',
        'description',
        'cover_storage_key',
        'published_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('published_at');
    }

    public function files(): HasMany
    {
        return $this->hasMany(EMagazineFile::class, 'magazine_publication_id');
    }
}
