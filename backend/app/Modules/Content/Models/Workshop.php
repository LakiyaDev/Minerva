<?php

declare(strict_types=1);

namespace App\Modules\Content\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A published workshop — schema v1.1 §4.5.1 (FR-03).
 */
class Workshop extends Model
{
    use HasUlids;

    protected $fillable = [
        'title',
        'description',
        'starts_at',
        'ends_at',
        'speaker_details',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'speaker_details' => 'array',
            'sort_order' => 'integer',
        ];
    }

    /** Only `published` rows reach public routes (FR-25). */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    /** Catalogue order: manual sort first, then the scheduled start. */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('starts_at');
    }

    public function resources(): HasMany
    {
        return $this->hasMany(WorkshopResource::class);
    }
}
