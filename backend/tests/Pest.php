<?php

declare(strict_types=1);

use App\Modules\Identity\Database\Seeders\RolePermissionSeeder;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');

function seedIdentity(): void
{
    test()->seed(RolePermissionSeeder::class);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function identityUser(string $role, array $attributes = []): User
{
    // Staff roles always carry a password credential in production; the
    // default keeps tests representative of that invariant.
    if (in_array($role, config('identity.staff.roles', []), true)) {
        $attributes['password'] ??= 'Password!2345';
    }

    $user = User::factory()->create($attributes);

    $roleModel = Role::query()->where('name', $role)->firstOrFail();

    $user->roles()->attach($roleModel->getKey());

    $user->flushPermissionCache();

    return $user->fresh(['roles']);
}

function enableTotp(User $user): string
{
    $secret = (new PragmaRX\Google2FA\Google2FA)->generateSecretKey(32);

    $user->forceFill([
        'two_factor_secret' => $secret,
        'two_factor_confirmed_at' => now(),
    ])->save();

    return $secret;
}
