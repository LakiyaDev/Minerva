<?php

declare(strict_types=1);

namespace App\Modules\Articles\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleAttachment extends Model
{
    use HasUlids;

    protected $fillable = [
        'article_id',
        'storage_disk',
        'storage_key',
        'original_name',
        'mime_type',
        'size_bytes',
        'kind',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
