<?php

declare(strict_types=1);

namespace App\Shared\Support;

use Illuminate\Http\Request;

final class IpHasher
{
    /**
     * Raw IPs are personal data. Audit/login records only retain a keyed hash
     * so that repeated abuse can be correlated without storing the address.
     */
    public static function hash(?string $ip, ?Request $request = null): ?string
    {
        $value = $ip ?? $request?->ip();

        if ($value === null || $value === '') {
            return null;
        }

        return hash_hmac('sha256', $value, (string) config('app.key'));
    }
}
