<?php

declare(strict_types=1);

namespace App\Modules\Articles\Http\Resources;

use App\Modules\Articles\Models\ArticleCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ArticleCategory */
class ArticleCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            // Only present on categories loaded with withCount().
            'articles_count' => $this->whenCounted('articles'),
        ];
    }
}
