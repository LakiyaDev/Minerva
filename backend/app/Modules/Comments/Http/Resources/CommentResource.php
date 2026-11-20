<?php

declare(strict_types=1);

namespace App\Modules\Comments\Http\Resources;

use App\Modules\Comments\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Comment */
class CommentResource extends JsonResource
{
    public const REMOVED_MASK = 'This comment was removed by a moderator';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isAdminRoute = $request->is('api/v1/admin/*');
        $isRemoved = $this->isRemoved();

        return [
            'id' => $this->getKey(),
            'article_id' => $this->article_id,
            'body' => ($isRemoved && ! $isAdminRoute) ? self::REMOVED_MASK : $this->body,
            'status' => $this->status->value,
            'is_removed' => $isRemoved,
            'author' => $this->whenLoaded('author', fn (): array => [
                'id' => $this->author->getKey(),
                'name' => $this->author->name,
            ]),
            'article' => $this->whenLoaded('article', fn (): array => [
                'id' => $this->article->getKey(),
                'title' => $this->article->title,
                'slug' => $this->article->slug,
            ]),
            'moderation_records' => $this->when(
                $isAdminRoute && $this->relationLoaded('moderationRecords'),
                fn () => CommentModerationRecordResource::collection($this->moderationRecords)->resolve($request),
            ),
            'removed_at' => $this->removed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
