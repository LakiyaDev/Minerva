<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\User;
use App\Shared\Contracts\AuditLogger;
use Illuminate\Http\Request;

final class ProfileService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Email is intentionally immutable here: it is the identity key for both
     * Google and staff accounts. Changing it requires a separate, verified
     * re-authentication flow.
     */
    public function updateName(User $user, string $name, Request $request): User
    {
        $user->forceFill(['name' => trim($name)])->save();

        $this->audit->record('identity.profile.updated', $user, $user, [], $request);

        return $user;
    }
}
