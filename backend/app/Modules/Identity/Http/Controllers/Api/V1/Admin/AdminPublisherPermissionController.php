<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\Api\V1\Admin;

use App\Modules\Identity\Http\Requests\GrantPublisherPermissionRequest;
use App\Modules\Identity\Http\Requests\UpdatePublisherPermissionRequest;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\PublisherPermissionService;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminPublisherPermissionController
{
    public function store(
        GrantPublisherPermissionRequest $request,
        User $user,
        PublisherPermissionService $publishers,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $grant = $publishers->grant(
            $user,
            $request->boolean('can_publish_directly'),
            $actor,
            $request,
        );

        return ApiResponse::created($this->payload($user, $grant->can_publish_directly));
    }

    public function update(
        UpdatePublisherPermissionRequest $request,
        User $user,
        PublisherPermissionService $publishers,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $grant = $publishers->updateDirectPublish(
            $user,
            $request->boolean('can_publish_directly'),
            $actor,
            $request,
        );

        return ApiResponse::data($this->payload($user, $grant->can_publish_directly));
    }

    public function destroy(
        Request $request,
        User $user,
        PublisherPermissionService $publishers,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        $publishers->revoke($user, $actor, $request);

        return ApiResponse::message('Publisher permission revoked.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(User $user, bool $canPublishDirectly): array
    {
        return [
            'user_id' => $user->getKey(),
            'can_publish_directly' => $canPublishDirectly,
        ];
    }
}
