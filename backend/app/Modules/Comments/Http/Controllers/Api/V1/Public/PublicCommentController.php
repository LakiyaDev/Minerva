<?php

declare(strict_types=1);

namespace App\Modules\Comments\Http\Controllers\Api\V1\Public;

use App\Modules\Articles\Models\Article;
use App\Modules\Comments\Exceptions\CommentException;
use App\Modules\Comments\Http\Resources\CommentResource;
use App\Modules\Comments\Models\Comment;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublicCommentController
{
    public function index(Request $request, Article $article): JsonResponse
    {
        if (! $article->isPublic()) {
            throw CommentException::articleNotPublished();
        }

        $perPage = min(100, max(1, $request->integer('per_page', 20)));

        $comments = Comment::query()
            ->where('article_id', $article->getKey())
            ->with('author:id,name')
            ->orderBy('created_at')
            ->paginate($perPage);

        return ApiResponse::data(CommentResource::collection($comments));
    }
}
