<?php

declare(strict_types=1);

namespace App\Modules\Comments\Http\Controllers\Api\V1\Admin;

use App\Modules\Comments\Enums\CommentStatus;
use App\Modules\Comments\Http\Requests\ModerateCommentRequest;
use App\Modules\Comments\Http\Resources\CommentResource;
use App\Modules\Comments\Models\Comment;
use App\Modules\Comments\Services\CommentService;
use App\Modules\Identity\Models\User;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class AdminCommentController
{
    public function __construct(private readonly CommentService $comments) {}

    public function index(Request $request): JsonResponse
    {
        $status = CommentStatus::tryFrom((string) $request->input('status', ''));
        $perPage = min(100, max(1, $request->integer('per_page', 20)));

        $comments = Comment::query()
            ->with([
                'author:id,name',
                'article:id,title,slug',
                'moderationRecords.actor:id,name',
            ])
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return ApiResponse::data(CommentResource::collection($comments));
    }

    public function remove(ModerateCommentRequest $request, Comment $comment): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        Gate::forUser($actor)->authorize('moderate', Comment::class);

        $removed = $this->comments->removeByModerator(
            $comment,
            $actor,
            $request->string('reason')->toString(),
        );

        return ApiResponse::data(new CommentResource($removed));
    }
}
