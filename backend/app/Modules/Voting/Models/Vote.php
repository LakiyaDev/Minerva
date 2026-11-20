<?php

declare(strict_types=1);

namespace App\Modules\Voting\Models;

use App\Modules\Articles\Models\Article;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only vote record. Finality is enforced by omitting update/delete
 * paths and maintaining a unique database constraint on (user_id, article_id).
 */
class Vote extends Model
{
    use HasUlids;

    /** @var bool */
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'article_id',
        'voting_period_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function votingPeriod(): BelongsTo
    {
        return $this->belongsTo(VotingPeriod::class);
    }
}
