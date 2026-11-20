<?php

declare(strict_types=1);

namespace App\Modules\Articles\Models;

use App\Modules\Articles\Database\Factories\ArticleCategoryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArticleCategory extends Model
{
    /** @use HasFactory<ArticleCategoryFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    public function articles(): HasMany
    {
        // The foreign key is explicit: the articles table uses category_id,
        // not the Laravel default article_category_id.
        return $this->hasMany(Article::class, 'category_id');
    }

    protected static function newFactory(): ArticleCategoryFactory
    {
        return ArticleCategoryFactory::new();
    }
}
