<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Api\V1\Admin;

use App\Modules\Identity\Http\Requests\InviteStaffRequest;
use App\Modules\Identity\Http\Resources\StaffInvitationResource;
use App\Modules\Identity\Models\StaffInvitation;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\StaffInvitationService;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

final class AdminStaffInvitationController
{
    public function index(): JsonResponse
    {
        $invitations = StaffInvitation::query()
            ->with('invitedBy')
            ->orderByDesc('created_at')
            ->paginate(50);

        return ApiResponse::data(StaffInvitationResource::collection($invitations));
    }

    public function store(
        InviteStaffRequest $request,
        StaffInvitationService $invitations,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $invitation = $invitations->invite(
            $actor,
            (string) $request->string('email'),
            (string) $request->string('role'),
            $request,
        );

        return ApiResponse::created(
            StaffInvitationResource::make($invitation->load('invitedBy'))
        );
    }
}
