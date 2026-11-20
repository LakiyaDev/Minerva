<?php

declare(strict_types=1);

namespace App\Modules\Comments\Database\Factories;

use App\Modules\Articles\Models\Article;
use App\Modules\Comments\Enums\CommentStatus;
use App\Modules\Comments\Models\Comment;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    protected $model = Comment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'article_id' => Article::factory(),
            'user_id' => User::factory(),
            'body' => fake()->paragraph(),
            'status' => CommentStatus::Visible,
        ];
    }

    public function removed(?User $moderator = null): static
    {
        return $this->state(fn (): array => [
            'status' => CommentStatus::Removed,
            'removed_at' => now(),
            'removed_by' => $moderator?->getKey(),
        ]);
    }
}
