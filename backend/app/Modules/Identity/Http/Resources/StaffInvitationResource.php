<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Modules\Identity\Models\StaffInvitation */
class StaffInvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'email' => $this->email,
            'role' => $this->role,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'invited_by' => $this->whenLoaded('invitedBy', fn () => $this->invitedBy?->name),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
