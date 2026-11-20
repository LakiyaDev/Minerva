<?php

declare(strict_types=1);

namespace App\Modules\Audit\Http\Resources;

use App\Modules\Audit\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuditLog
 */
final class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'action' => (string) $this->action,
            'actor' => $this->actor ? [
                'id' => (string) $this->actor->id,
                'name' => (string) $this->actor->name,
                'email' => (string) $this->actor->email,
            ] : null,
            'subject_type' => $this->subject_type ? (string) $this->subject_type : null,
            'subject_id' => $this->subject_id ? (string) $this->subject_id : null,
            'metadata' => (array) ($this->metadata ?? []),
            'ip_hash' => $this->ip_hash ? (string) $this->ip_hash : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
