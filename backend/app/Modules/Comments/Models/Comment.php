<?php

declare(strict_types=1);

namespace App\Modules\Comments\Models;

use App\Modules\Articles\Models\Article;
use App\Modules\Comments\Database\Factories\CommentFactory;
use App\Modules\Comments\Enums\CommentStatus;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'article_id',
        'user_id',
        'body',
        'status',
        'removed_at',
        'removed_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => CommentStatus::class,
            'removed_at' => 'datetime',
        ];
    }

    public function isRemoved(): bool
    {
        return $this->status === CommentStatus::Removed;
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function remover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'removed_by');
    }

    public function moderationRecords(): HasMany
    {
        return $this->hasMany(CommentModerationRecord::class);
    }

    protected static function newFactory(): CommentFactory
    {
        return CommentFactory::new();
    }
}
