<?php

declare(strict_types=1);

namespace App\Modules\Voting\Http\Resources;

use App\Modules\Voting\Models\Vote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Vote */
class VoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'article_id' => $this->article_id,
            'voting_period_id' => $this->voting_period_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'voting_period' => $this->whenLoaded('votingPeriod', fn (): array => [
                'id' => $this->votingPeriod->getKey(),
                'name' => $this->votingPeriod->name,
            ]),
            'article' => $this->whenLoaded('article', fn (): ?array => $this->article === null ? null : [
                'id' => $this->article->getKey(),
                'title' => $this->article->title,
                'slug' => $this->article->slug,
                'category' => $this->article->relationLoaded('category') && $this->article->category !== null ? [
                    'id' => $this->article->category->getKey(),
                    'name' => $this->article->category->name,
                    'slug' => $this->article->category->slug,
                ] : null,
                'author' => $this->article->relationLoaded('author') && $this->article->author !== null ? [
                    'id' => $this->article->author->getKey(),
                    'name' => $this->article->author->name,
                ] : null,
            ]),
        ];
    }
}
