<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Api\V1;

use App\Modules\Identity\Http\Requests\UpdateProfileRequest;
use App\Modules\Identity\Http\Resources\MeResource;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\ProfileService;
use Illuminate\Http\Request;

final class MeController
{
    public function show(Request $request): MeResource
    {
        /** @var User $user */
        $user = $request->user();

        return MeResource::make($user->loadMissing('roles'));
    }

    public function update(UpdateProfileRequest $request, ProfileService $profiles): MeResource
    {
        /** @var User $user */
        $user = $request->user();

        $profiles->updateName($user, (string) $request->string('name'), $request);

        return MeResource::make($user->fresh(['roles']));
    }
}
