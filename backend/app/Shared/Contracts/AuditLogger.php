<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

interface AuditLogger
{
    /**
     * Append an immutable audit record. Implementations must never update or
     * delete existing rows.
     *
     * @param  Model|string|null  $subject  Model instance or subject type string
     * @param  array<string, mixed>  $metadata  Non-sensitive structured context
     */
    public function record(
        string $action,
        ?Authenticatable $actor = null,
        Model|string|null $subject = null,
        array $metadata = [],
        ?Request $request = null,
    ): void;
}
