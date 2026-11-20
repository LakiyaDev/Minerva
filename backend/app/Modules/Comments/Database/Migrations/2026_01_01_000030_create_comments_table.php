<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('article_id')->constrained('articles')->restrictOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->string('status', 20)->default('visible');
            $table->timestamp('removed_at')->nullable();
            $table->foreignUlid('removed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['article_id', 'created_at'], 'comments_article_created_at_index');
            $table->index(['user_id', 'created_at'], 'comments_user_created_at_index');
            $table->index(['status', 'created_at'], 'comments_status_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
