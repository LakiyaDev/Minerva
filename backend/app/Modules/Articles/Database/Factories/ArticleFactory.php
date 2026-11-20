<?php

declare(strict_types=1);

namespace App\Modules\Articles\Database\Factories;

use App\Modules\Articles\Enums\ArticleStatus;
use App\Modules\Articles\Models\Article;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Article>
 */
final class ArticleFactory extends Factory
{
    protected $model = Article::class;

    public function definition(): array
    {
        $title = Str::title($this->faker->unique()->words(5, true));

        return [
            'author_id' => User::factory()->withPassword(),
            'category_id' => ArticleCategoryFactory::new(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.$this->faker->unique()->numberBetween(1000, 9999),
            'abstract' => $this->faker->paragraph(),
            'body' => $this->faker->paragraphs(4, true),
            'status' => ArticleStatus::Draft,
            'is_featured' => false,
            'published_at' => null,
        ];
    }

    public function authoredBy(User $author): static
    {
        return $this->state(fn (): array => ['author_id' => $author->getKey()]);
    }

    public function withStatus(ArticleStatus $status): static
    {
        return $this->state(fn (): array => [
            'status' => $status,
            'published_at' => $status->isPublic() ? now()->subDay() : null,
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (): array => ['is_featured' => true]);
    }
}
