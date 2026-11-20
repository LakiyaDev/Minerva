<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Identity\Models\University;
use Illuminate\Database\Seeder;

/**
 * Seeds the controlled university lookup — schema v1.1 §4.1.9 (decision D1).
 *
 * Idempotent: re-running refreshes names and short names without creating
 * duplicates. Universities are never deleted by this seeder, because users
 * reference them and the FK is ON DELETE RESTRICT; retire one by setting
 * `is_active` to false in `database/data/universities.php` instead.
 */
final class UniversitySeeder extends Seeder
{
    public function run(): void
    {
        /** @var array<int, array{name: string, short_name: string|null, is_active?: bool}> $universities */
        $universities = require database_path('data/universities.php');

        foreach ($universities as $university) {
            University::query()->updateOrCreate(
                ['name' => $university['name']],
                [
                    'short_name' => $university['short_name'] ?? null,
                    'is_active' => $university['is_active'] ?? true,
                ],
            );
        }
    }
}
