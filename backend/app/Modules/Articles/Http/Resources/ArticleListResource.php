<?php

declare(strict_types=1);

namespace App\Modules\Articles\Http\Resources;

use App\Modules\Articles\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Article */
class ArticleListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'title' => $this->title,
            'slug' => $this->slug,
            'abstract' => $this->abstract,
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
            // Vote counts are owned by the Voting module; it fills this in
            // once vote summaries exist.
            'votes_count' => 0,
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
