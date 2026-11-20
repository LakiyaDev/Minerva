<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Contracts\IdentityAccess;
use App\Modules\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class MeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $access = app(IdentityAccess::class);

        return [
            'id' => $this->getKey(),
            'name' => $this->name,
            'email' => $this->email,
            'email_verified' => $this->email_verified_at !== null,
            'is_active' => $this->is_active,
            'roles' => $this->roles->pluck('name')->values()->all(),
            'permissions' => $this->effectivePermissions(),
            'capabilities' => [
                'is_participant' => $access->isParticipant($this->resource),
                'is_publisher' => $access->isPublisher($this->resource),
                'can_publish_directly' => $access->canPublishDirectly($this->resource),
            ],
            'two_factor_enabled' => $this->hasConfirmedTwoFactor(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
