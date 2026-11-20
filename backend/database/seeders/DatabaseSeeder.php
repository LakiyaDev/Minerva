<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Articles\Database\Seeders\ArticleCategorySeeder;
use App\Modules\Identity\Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            ArticleCategorySeeder::class,
        ]);
    }
}
