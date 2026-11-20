<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Derived/cached vote totals; the votes table remains authoritative.
        Schema::create('article_vote_summaries', function (Blueprint $table): void {
            $table->engine = 'InnoDB';

            $table->foreignUlid('article_id')->primary()->constrained('articles')->restrictOnDelete();
            $table->unsignedInteger('vote_count')->default(0);
            $table->timestamp('updated_at')->useCurrent();

            $table->index('vote_count', 'article_vote_summaries_count_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_vote_summaries');
    }
};
