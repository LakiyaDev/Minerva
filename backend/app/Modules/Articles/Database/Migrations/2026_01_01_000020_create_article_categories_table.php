<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_categories', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name', 128);
            // Unique slug doubles as the only index this table needs for
            // public catalogue filtering.
            $table->string('slug', 128)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_categories');
    }
};
