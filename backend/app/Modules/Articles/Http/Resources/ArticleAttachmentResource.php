<?php

declare(strict_types=1);

namespace App\Modules\Articles\Http\Resources;

use App\Modules\Articles\Models\ArticleAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ArticleAttachment */
class ArticleAttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'kind' => $this->kind,
            'download_url' => "/api/v1/articles/{$this->article_id}/attachments/{$this->getKey()}/download",
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
