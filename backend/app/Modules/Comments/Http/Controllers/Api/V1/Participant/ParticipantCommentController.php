<?php

declare(strict_types=1);

namespace App\Modules\Comments\Http\Controllers\Api\V1\Participant;

use App\Modules\Articles\Models\Article;
use App\Modules\Comments\Http\Requests\StoreCommentRequest;
use App\Modules\Comments\Http\Resources\CommentResource;
use App\Modules\Comments\Models\Comment;
use App\Modules\Comments\Services\CommentService;
use App\Modules\Identity\Models\User;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ParticipantCommentController
{
    public function __construct(private readonly CommentService $comments) {}

    public function store(StoreCommentRequest $request, Article $article): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $comment = $this->comments->submit(
            $article,
            $actor,
            $request->string('body')->toString(),
        );

        return ApiResponse::created(new CommentResource($comment));
    }

    public function destroy(Request $request, Comment $comment): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        Gate::forUser($actor)->authorize('delete', $comment);

        $this->comments->deleteOwn($comment);

        return ApiResponse::message('Comment deleted.');
    }

    public function myComments(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $perPage = min(100, max(1, $request->integer('per_page', 20)));

        $comments = Comment::query()
            ->where('user_id', $actor->getKey())
            ->with(['author:id,name', 'article:id,title,slug'])
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return ApiResponse::data(CommentResource::collection($comments));
    }
}
