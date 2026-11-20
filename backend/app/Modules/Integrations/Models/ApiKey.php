<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A service API key for a trusted integration — schema v1.1 §4.6.1 and
 * backend.md §7.2.
 *
 * The plaintext key is `mnv_live_<public_id>_<secret>` and is shown exactly
 * once, at creation. Only `secret_hash` (SHA-256 hex) is persisted.
 */
class ApiKey extends Model
{
    use HasUlids;

    protected $fillable = [
        'public_id',
        'name',
        'secret_hash',
        'scopes',
        'created_by',
        'last_used_at',
        'expires_at',
        'revoked_at',
    ];

    protected $hidden = [
        'secret_hash',
    ];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** Keys that may still authenticate. */
    public function scopeUsable(Builder $query): Builder
    {
        return $query
            ->whereNull('revoked_at')
            ->where(function (Builder $inner): void {
                $inner->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function grants(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? [], true);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
