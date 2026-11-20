<?php

declare(strict_types=1);

namespace App\Modules\Articles\Http\Controllers\Api\V1\Publisher;

use App\Modules\Articles\Http\Requests\StoreArticleAttachmentRequest;
use App\Modules\Articles\Http\Resources\ArticleAttachmentResource;
use App\Modules\Articles\Models\Article;
use App\Modules\Articles\Models\ArticleAttachment;
use App\Modules\Articles\Services\ArticleAttachmentService;
use App\Modules\Identity\Models\User;
use App\Shared\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class PublisherArticleAttachmentController
{
    public function __construct(private readonly ArticleAttachmentService $attachments) {}

    public function store(StoreArticleAttachmentRequest $request, Article $article): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        Gate::forUser($actor)->authorize('uploadAttachment', $article);

        $kind = $request->string('kind', 'document')->toString() ?: 'document';

        $attachment = $this->attachments->storeAttachment(
            $article,
            $request->file('file'),
            $kind,
        );

        return ApiResponse::created(new ArticleAttachmentResource($attachment));
    }

    public function destroy(Request $request, Article $article, ArticleAttachment $attachment): JsonResponse
    {
        abort_if($attachment->article_id !== $article->getKey(), 404);

        /** @var User $actor */
        $actor = $request->user();

        Gate::forUser($actor)->authorize('deleteAttachment', $article);

        $this->attachments->deleteAttachment($attachment);

        return ApiResponse::noContent();
    }
}
