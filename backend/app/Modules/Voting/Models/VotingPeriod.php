<?php

declare(strict_types=1);

namespace App\Modules\Voting\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Voting\Database\Factories\VotingPeriodFactory;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VotingPeriod extends Model
{
    /** @use HasFactory<VotingPeriodFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'name',
        'starts_at',
        'ends_at',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isOpen(?CarbonInterface $at = null): bool
    {
        $now = $at ?? now();

        return $this->is_active
            && $this->starts_at !== null
            && $this->ends_at !== null
            && $this->starts_at->lte($now)
            && $this->ends_at->gte($now);
    }

    public function isUpcoming(?CarbonInterface $at = null): bool
    {
        $now = $at ?? now();

        return $this->is_active
            && $this->starts_at !== null
            && $this->starts_at->gt($now);
    }

    public function isEnded(?CarbonInterface $at = null): bool
    {
        $now = $at ?? now();

        return ! $this->is_active
            || ($this->ends_at !== null && $this->ends_at->lt($now));
    }

    public function windowStatus(?CarbonInterface $at = null): string
    {
        if ($this->isOpen($at)) {
            return 'open';
        }

        if ($this->isUpcoming($at)) {
            return 'upcoming';
        }

        return 'closed';
    }

    public function timeRemainingSeconds(?CarbonInterface $at = null): int
    {
        $now = $at ?? now();

        if (! $this->isOpen($now) || $this->ends_at === null) {
            return 0;
        }

        return max(0, (int) $now->diffInSeconds($this->ends_at, false));
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function newFactory(): VotingPeriodFactory
    {
        return VotingPeriodFactory::new();
    }
}
