<?php

declare(strict_types=1);

namespace App\Modules\Voting\Http\Resources;

use App\Modules\Voting\Models\VotingPeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin VotingPeriod */
class VotingPeriodResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $now = now();

        return [
            'id' => $this->getKey(),
            'name' => $this->name,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'is_active' => $this->is_active,
            'status' => $this->windowStatus($now),
            'is_open' => $this->isOpen($now),
            'time_remaining_seconds' => $this->timeRemainingSeconds($now),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
