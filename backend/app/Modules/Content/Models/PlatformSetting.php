<?php

declare(strict_types=1);

namespace App\Modules\Content\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dashboard-managed site content and configuration — schema v1.1 §4.8
 * (FR-10, decision D2).
 *
 * Values are JSON so a single table can hold hero copy, list-shaped rules and
 * simple feature flags without schema changes.
 */
class PlatformSetting extends Model
{
    use HasUlids;

    protected $fillable = [
        'setting_group',
        'setting_key',
        'value',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
