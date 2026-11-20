<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('voting-results.{periodId}', function (User $user, string $periodId): bool {
    return $user->isActive() && $user->hasPermission('participant.view_results');
});
