<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Not final on purpose: Sanctum's test helper (Sanctum::actingAs) mocks this
 * model, and Mockery cannot mock final classes.
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use HasUlids;
}
