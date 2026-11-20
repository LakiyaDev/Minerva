<?php

declare(strict_types=1);

namespace App\Modules\Comments\Http\Resources;

use App\Modules\Comments\Models\CommentModerationRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CommentModerationRecord */
class CommentModerationRecordResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'action' => $this->action,
            'reason' => $this->reason,
            'actor' => $this->whenLoaded('actor', fn (): array => [
                'id' => $this->actor->getKey(),
                'name' => $this->actor->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
