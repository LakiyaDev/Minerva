<?php

declare(strict_types=1);

namespace App\Modules\Articles\Http\Resources;

use App\Modules\Articles\Models\Article;
use App\Modules\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/** @mixin Article */
class ArticleDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->getKey(),
            'title' => $this->title,
            'slug' => $this->slug,
            'abstract' => $this->abstract,
            'body' => $this->body,
            'category' => $this->whenLoaded('category', fn (): array => [
                'id' => $this->category->getKey(),
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),
            'author' => $this->whenLoaded('author', fn (): array => [
                'id' => $this->author->getKey(),
                'name' => $this->author->name,
            ]),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_featured' => $this->is_featured,
            'votes_count' => 0,
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'attachments' => $this->whenLoaded(
                'attachments',
                fn (): array => ArticleAttachmentResource::collection($this->attachments)->resolve($request),
            ),
            // Workflow evidence is restricted to owners and editorial staff.
            'status_history' => $this->when(
                $user instanceof User && Gate::forUser($user)->allows('viewOwn', $this->resource),
                fn (): array => $this->statusHistory
                    ->map(fn ($entry): array => [
                        'from_status' => $entry->from_status,
                        'to_status' => $entry->to_status,
                        'reason' => $entry->reason,
                        'acted_by' => $entry->acted_by,
                        'created_at' => $entry->created_at?->toIso8601String(),
                    ])
                    ->values()
                    ->all(),
            ),
        ];
    }
}
