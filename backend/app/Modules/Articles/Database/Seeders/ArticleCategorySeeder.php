<?php

declare(strict_types=1);

namespace App\Modules\Articles\Database\Seeders;

use App\Modules\Articles\Models\ArticleCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class ArticleCategorySeeder extends Seeder
{
    /**
     * Approved reference data for the editorial programme. Idempotent so the
     * seeder is safe to re-run in every environment.
     */
    public function run(): void
    {
        $categories = [
            'ai-in-medicine' => [
                'name' => 'AI in Medicine',
                'description' => 'Artificial intelligence in diagnosis, care and clinical decision-making',
            ],
            'bioinformatics' => [
                'name' => 'Bioinformatics',
                'description' => 'Computation applied to biological and genomic data',
            ],
            'medical-devices' => [
                'name' => 'Medical Devices',
                'description' => 'Low-cost instrumentation and biomedical engineering',
            ],
            'science-communication' => [
                'name' => 'Science Communication',
                'description' => 'How science is explained, visualised and debated in public',
            ],
            'sustainability' => [
                'name' => 'Sustainability',
                'description' => 'Environmental, energy and public-health sustainability',
            ],
        ];

        DB::transaction(function () use ($categories): void {
            foreach ($categories as $slug => $category) {
                ArticleCategory::query()->firstOrCreate(
                    ['slug' => $slug],
                    ['name' => $category['name'], 'description' => $category['description']],
                );
            }
        });
    }
}
