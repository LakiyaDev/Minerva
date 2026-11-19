<?php

declare(strict_types=1);

namespace App\Shared\Support;

use Illuminate\Support\Str;

final class EmailNormalizer
{
    /**
     * Emails are stored lowercase and trimmed. A single canonical form keeps
     * the unique index meaningful and prevents duplicate accounts that differ
     * only by case.
     */
    public static function normalize(?string $email): string
    {
        return Str::lower(trim((string) $email));
    }
}
