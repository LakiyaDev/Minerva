<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Not final on purpose: Sanctum's test helper (Sanctum::actingAs) mocks this
 * model, and Mockery cannot mock final classes.
 *
 * The id column is a standard auto-increment bigint (Sanctum's own migration
 * shape); tokenable ids remain ULIDs, like every other key in the codebase.
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    //
}
