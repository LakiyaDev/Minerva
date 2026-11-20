<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

// Not final: Sanctum's Sanctum::actingAs() test helper mocks this model.
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use HasUlids;
}
