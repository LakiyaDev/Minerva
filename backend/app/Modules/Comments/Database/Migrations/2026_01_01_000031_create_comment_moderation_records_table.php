<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comment_moderation_records', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('comment_id')->constrained('comments')->cascadeOnDelete();
            $table->string('action', 20);
            $table->string('reason', 500)->nullable();
            $table->foreignUlid('acted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index('comment_id');
            $table->index(['acted_by', 'created_at'], 'comment_mod_records_acted_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comment_moderation_records');
    }
};
