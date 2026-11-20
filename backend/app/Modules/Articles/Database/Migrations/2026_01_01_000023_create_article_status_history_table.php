<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only status transition log. No updated_at, no update/delete
        // path anywhere in the module: the rows are evidence of workflow.
        Schema::create('article_status_history', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('article_id')->constrained('articles')->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->foreignUlid('acted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['article_id', 'created_at'], 'article_status_history_article_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_status_history');
    }
};
