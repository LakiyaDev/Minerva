<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Str;

final class OAuthStateService
{
    public function __construct(private readonly Cache $cache) {}

    /**
     * Issue a single-use, server-side OAuth state value. It protects the
     * callback against login CSRF and is never trusted from the client.
     */
    public function issue(): string
    {
        $state = Str::random(64);

        $this->cache->put(
            $this->key($state),
            true,
            now()->addSeconds((int) config('identity.google.state_ttl_seconds', 600)),
        );

        return $state;
    }

    public function consume(string $state): bool
    {
        if ($state === '') {
            return false;
        }

        return (bool) $this->cache->pull($this->key($state), false);
    }

    private function key(string $state): string
    {
        return 'identity:oauth:state:'.hash('sha256', $state);
    }
}
