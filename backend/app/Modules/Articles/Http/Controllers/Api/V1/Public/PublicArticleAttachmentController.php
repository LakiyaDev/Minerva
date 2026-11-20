<?php

declare(strict_types=1);

namespace App\Modules\Articles\Http\Controllers\Api\V1\Public;

use App\Modules\Articles\Exceptions\ArticleException;
use App\Modules\Articles\Models\Article;
use App\Modules\Articles\Models\ArticleAttachment;
use App\Modules\Articles\Services\ArticleAttachmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PublicArticleAttachmentController
{
    public function __construct(private readonly ArticleAttachmentService $attachments) {}

    public function download(
        Request $request,
        Article $article,
        ArticleAttachment $attachment,
    ): StreamedResponse {
        abort_if($attachment->article_id !== $article->getKey(), 404);

        $user = $request->user('sanctum') ?? $request->user();

        if (! Gate::forUser($user)->allows('downloadAttachment', $article)) {
            throw ArticleException::notFound();
        }

        return $this->attachments->downloadResponse($attachment);
    }
}
