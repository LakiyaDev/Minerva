<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Api\V1\Auth;

use App\Modules\Identity\Http\Requests\AcceptStaffInvitationRequest;
use App\Modules\Identity\Http\Resources\MeResource;
use App\Modules\Identity\Services\SessionService;
use App\Modules\Identity\Services\StaffInvitationService;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

final class StaffInvitationAcceptController
{
    public function __invoke(
        AcceptStaffInvitationRequest $request,
        StaffInvitationService $invitations,
        SessionService $sessions,
    ): JsonResponse {
        $user = $invitations->accept(
            (string) $request->string('token'),
            (string) $request->string('name'),
            (string) $request->string('password'),
            $request,
        );

        $sessions->login($user, $request, 'staff_invitation');

        return ApiResponse::created([
            'user' => new MeResource($user->load('roles')),
        ]);
    }
}
