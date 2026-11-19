<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Modules\Identity\Models\User */
class AdminUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $activeGrant = $this->relationLoaded('publisherGrants')
            ? $this->publisherGrants->firstWhere('revoked_at', null)
            : null;

        return [
            'id' => $this->getKey(),
            'name' => $this->name,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'email_verified' => $this->email_verified_at !== null,
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')->values()->all()),
            'direct_permissions' => $this->whenLoaded('directPermissions', fn () => $this->directPermissions
                ->map(fn ($permission) => [
                    'name' => $permission->name,
                    'expires_at' => $permission->pivot->expires_at,
                ])
                ->values()
                ->all()),
            'publisher_permission' => $activeGrant === null ? null : [
                'can_publish_directly' => $activeGrant->can_publish_directly,
                'granted_at' => $activeGrant->granted_at?->toIso8601String(),
            ],
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
