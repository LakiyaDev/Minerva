<?php

declare(strict_types=1);

namespace App\Modules\Content\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A downloadable file belonging to a magazine issue — schema v1.1 §4.5.7
 * (FR-09). Only the private S3 key is stored; delivery is signed.
 */
class EMagazineFile extends Model
{
    use HasUlids;

    protected $fillable = [
        'magazine_publication_id',
        'storage_key',
        'mime_type',
        'size_bytes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function publication(): BelongsTo
    {
        return $this->belongsTo(MagazinePublication::class, 'magazine_publication_id');
    }
}
