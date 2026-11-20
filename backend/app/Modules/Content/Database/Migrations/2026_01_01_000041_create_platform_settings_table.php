<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dashboard-managed site content and configuration — schema v1.1 §4.8 and
 * decision D2 (FR-10). Content owns this table because every row is public
 * website copy or public configuration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            // e.g. "competition", "homepage".
            $table->string('setting_group', 100);
            $table->string('setting_key', 150);
            // JSON keeps heterogeneous values (strings, lists, objects) in one
            // column without a widening table.
            $table->json('value')->nullable();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One row per group + key pair.
            $table->unique(['setting_group', 'setting_key'], 'platform_settings_group_key_unique');
            $table->index('setting_group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
