<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only vote records. Finality is enforced by omitting update/delete
        // endpoints and enforcing a strict unique index on (user_id, article_id).
        Schema::create('votes', function (Blueprint $table): void {
            $table->engine = 'InnoDB';

            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('article_id')->constrained('articles')->restrictOnDelete();
            $table->foreignUlid('voting_period_id')->constrained('voting_periods')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'article_id'], 'votes_one_per_user_article');
            $table->index('article_id', 'votes_article_index');
            $table->index('voting_period_id', 'votes_period_index');
            $table->index('user_id', 'votes_user_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('votes');
    }
};
