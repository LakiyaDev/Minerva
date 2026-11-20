<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_attachments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('article_id')->constrained('articles')->cascadeOnDelete();
            // Storage metadata only: never store public URLs or secrets.
            $table->string('storage_disk', 32)->default('s3');
            $table->string('storage_key', 512);
            $table->string('original_name', 255);
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('size_bytes');
            $table->string('kind', 20)->default('document');
            $table->timestamps();

            $table->index(['article_id', 'kind'], 'article_attachments_article_kind_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_attachments');
    }
};
