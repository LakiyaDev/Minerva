<?php

declare(strict_types=1);

namespace App\Modules\Audit\Services;

use App\Modules\Audit\Models\AuditLog;
use App\Shared\Contracts\AuditLogger;
use App\Shared\Support\IpHasher;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class DatabaseAuditLogger implements AuditLogger
{
    /**
     * Keys that must never reach the audit trail.
     */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'token',
        'access_token',
        'refresh_token',
        'secret',
        'two_factor_secret',
        'recovery_codes',
        'authorization',
        'cookie',
        'code',
    ];

    public function record(
        string $action,
        ?Authenticatable $actor = null,
        Model|string|null $subject = null,
        array $metadata = [],
        ?Request $request = null,
    ): void {
        $subjectType = null;
        $subjectId = null;

        if ($subject instanceof Model) {
            $subjectType = $subject->getMorphClass();
            $subjectId = (string) $subject->getKey();
        } elseif (is_string($subject) && $subject !== '') {
            $subjectType = $subject;
        }

        AuditLog::query()->create([
            'actor_id' => $actor?->getAuthIdentifier(),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'metadata' => $this->sanitize($metadata),
            'ip_hash' => IpHasher::hash(null, $request),
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function sanitize(array $metadata): array
    {
        $clean = [];

        foreach ($metadata as $key => $value) {
            if (in_array(strtolower((string) $key), self::SENSITIVE_KEYS, true)) {
                $clean[$key] = '[redacted]';

                continue;
            }

            $clean[$key] = is_array($value) ? $this->sanitize($value) : $value;
        }

        return $clean;
    }
}
