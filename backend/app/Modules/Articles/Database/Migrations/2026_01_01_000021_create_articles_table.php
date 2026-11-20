<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            // Retention rule: articles, votes, comments and audit records must
            // survive hard deletes of other tables, so the reference is
            // restricted rather than cascaded.
            $table->foreignUlid('author_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('category_id')->constrained('article_categories')->restrictOnDelete();
            $table->string('title', 255);
            $table->string('slug', 255)->unique();
            $table->text('abstract');
            $table->mediumText('body');
            // Allowed values: draft, pending_review, approved, rejected,
            // published, unpublished. Enforced by the workflow state machine.
            $table->string('status', 32)->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            // Schema v1.1 §1: articles are never soft-deleted; an article that
            // leaves the catalogue moves to the `unpublished` status.
            $table->index(['status', 'published_at'], 'articles_status_published_index');
            $table->index(['author_id', 'status'], 'articles_author_status_index');
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
