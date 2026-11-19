<?php

declare(strict_types=1);

use App\Shared\Support\EmailNormalizer;

it('normalizes email addresses to a single canonical form', function (): void {
    expect(EmailNormalizer::normalize('  Reader@Example.COM '))->toBe('reader@example.com')
        ->and(EmailNormalizer::normalize('STAFF@Example.lk'))->toBe('staff@example.lk')
        ->and(EmailNormalizer::normalize(null))->toBe('');
});
