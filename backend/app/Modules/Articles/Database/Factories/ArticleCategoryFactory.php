<?php

declare(strict_types=1);

namespace App\Modules\Articles\Database\Factories;

use App\Modules\Articles\Models\ArticleCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ArticleCategory>
 */
final class ArticleCategoryFactory extends Factory
{
    protected $model = ArticleCategory::class;

    public function definition(): array
    {
        $name = Str::title($this->faker->unique()->words(2, true));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $this->faker->sentence(),
        ];
    }
}
