<?php

declare(strict_types=1);

namespace App\Modules\Voting\Models;

use App\Modules\Articles\Models\Article;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Derived read-model cache of total votes per article. The votes table
 * remains the authoritative source of truth.
 */
class ArticleVoteSummary extends Model
{
    /** @var bool */
    public $incrementing = false;

    /** @var string */
    protected $keyType = 'string';

    /** @var string */
    protected $primaryKey = 'article_id';

    /** @var bool */
    public $timestamps = false;

    protected $fillable = [
        'article_id',
        'vote_count',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'vote_count' => 'integer',
            'updated_at' => 'datetime',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
