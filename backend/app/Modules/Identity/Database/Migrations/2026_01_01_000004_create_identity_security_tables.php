<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_invitations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('email');
            $table->string('role', 64);
            $table->foreignUlid('invited_by')->nullable()->constrained('users')->nullOnDelete();
            // Only the SHA-256 hash of the invitation token is stored.
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['email', 'accepted_at']);
        });

        Schema::create('login_attempts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('email');
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_hash', 64)->nullable();
            $table->boolean('successful')->default(false);
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('attempted_at');

            $table->index('email');
            $table->index(['email', 'attempted_at']);
            $table->index('ip_hash');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->ulid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            // Standard Sanctum shape (schema v1.1 §4.8): auto-increment id.
            // `tokenable_id` stays a ULID because every other key in this
            // codebase is one (deviation V1).
            $table->id();
            $table->ulidMorphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('login_attempts');
        Schema::dropIfExists('staff_invitations');
    }
};
