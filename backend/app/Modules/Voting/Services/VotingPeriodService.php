<?php

declare(strict_types=1);

namespace App\Modules\Voting\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Voting\Models\VotingPeriod;
use App\Shared\Contracts\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class VotingPeriodService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function current(): ?VotingPeriod
    {
        return VotingPeriod::query()
            ->where('is_active', true)
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * @param  array{name: string, starts_at: mixed, ends_at: mixed, is_active?: bool}  $attributes
     */
    public function create(User $actor, array $attributes, ?Request $request = null): VotingPeriod
    {
        $isActive = (bool) ($attributes['is_active'] ?? false);

        $period = DB::transaction(function () use ($actor, $attributes, $isActive, $request): VotingPeriod {
            if ($isActive) {
                VotingPeriod::query()
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }

            $period = VotingPeriod::query()->create([
                'name' => $attributes['name'],
                'starts_at' => $attributes['starts_at'],
                'ends_at' => $attributes['ends_at'],
                'is_active' => $isActive,
                'created_by' => $actor->getKey(),
            ]);

            $this->audit->record(
                'voting_period.created',
                $actor,
                $period,
                [
                    'name' => $period->name,
                    'starts_at' => $period->starts_at?->toIso8601String(),
                    'ends_at' => $period->ends_at?->toIso8601String(),
                    'is_active' => $period->is_active,
                ],
                $request,
            );

            return $period;
        });

        return $period->refresh();
    }

    /**
     * @param  array{name?: string, starts_at?: mixed, ends_at?: mixed, is_active?: bool}  $attributes
     */
    public function update(
        User $actor,
        VotingPeriod $period,
        array $attributes,
        ?Request $request = null,
    ): VotingPeriod {
        $activating = ($attributes['is_active'] ?? false) === true;

        $updated = DB::transaction(function () use ($actor, $period, $attributes, $activating, $request): VotingPeriod {
            if ($activating) {
                VotingPeriod::query()
                    ->where('id', '!=', $period->getKey())
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }

            $period->fill($attributes);
            $period->save();

            $this->audit->record(
                'voting_period.updated',
                $actor,
                $period,
                [
                    'name' => $period->name,
                    'starts_at' => $period->starts_at?->toIso8601String(),
                    'ends_at' => $period->ends_at?->toIso8601String(),
                    'is_active' => $period->is_active,
                ],
                $request,
            );

            return $period;
        });

        return $updated->refresh();
    }

    public function activate(User $actor, VotingPeriod $period, ?Request $request = null): VotingPeriod
    {
        $activated = DB::transaction(function () use ($actor, $period, $request): VotingPeriod {
            VotingPeriod::query()
                ->where('id', '!=', $period->getKey())
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $period->is_active = true;
            $period->save();

            $this->audit->record(
                'voting_period.activated',
                $actor,
                $period,
                [
                    'name' => $period->name,
                    'starts_at' => $period->starts_at?->toIso8601String(),
                    'ends_at' => $period->ends_at?->toIso8601String(),
                ],
                $request,
            );

            return $period;
        });

        return $activated->refresh();
    }
}
