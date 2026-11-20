<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Service API keys for trusted integrations — schema v1.1 §4.6.1 and
 * backend.md §7.2.
 *
 * Only the SHA-256 hash of the secret is stored. The plaintext is shown once,
 * at creation time, and never again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            // Embedded in `mnv_live_<public_id>_<secret>`.
            $table->string('public_id', 64)->unique();
            $table->string('name', 150);
            // SHA-256 hex digest of the secret half.
            $table->char('secret_hash', 64);
            // e.g. ["analytics.read","content.read"].
            $table->json('scopes');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            // Refreshed asynchronously so authentication stays read-mostly.
            $table->timestamp('last_used_at')->nullable();
            // Every new key is issued with an expiry.
            $table->timestamp('expires_at')->nullable();
            // Immediate revocation without deleting the audit trail.
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index('expires_at');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
