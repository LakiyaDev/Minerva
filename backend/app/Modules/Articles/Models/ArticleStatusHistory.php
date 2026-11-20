<?php

declare(strict_types=1);

namespace App\Modules\Articles\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only editorial workflow evidence. There is intentionally no update
 * or delete path in this model or anywhere else in the module.
 */
class ArticleStatusHistory extends Model
{
    use HasUlids;

    /** @var bool */
    public $timestamps = false;

    /**
     * The workflow log is singular by design: "article_status_history" reads
     * as the history *of* an article rather than a collection of rows.
     */
    protected $table = 'article_status_history';

    protected $fillable = [
        'article_id',
        'from_status',
        'to_status',
        'acted_by',
        'reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }
}
