<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only. No updated_at: audit history is never modified.
        // `actor_id` carries a foreign key with ON DELETE SET NULL, so a row
        // survives even if the acting account is ever purged (schema v1.1
        // §4.7.1). The polymorphic `subject_type`/`subject_id` pair cannot
        // carry a foreign key by definition.
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 128);
            $table->string('subject_type', 191)->nullable();
            $table->string('subject_id', 191)->nullable();
            $table->json('metadata')->nullable();
            $table->char('ip_hash', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['actor_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
