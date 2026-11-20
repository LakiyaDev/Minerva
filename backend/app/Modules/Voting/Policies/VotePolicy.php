<?php

declare(strict_types=1);

namespace App\Modules\Voting\Policies;

use App\Modules\Identity\Models\User;

final class VotePolicy
{
    public function vote(User $user): bool
    {
        return $user->hasPermission('participant.vote');
    }

    public function viewResults(User $user): bool
    {
        return $user->hasPermission('participant.view_results');
    }

    public function managePeriods(User $user): bool
    {
        return $user->hasPermission('settings.manage');
    }
}
