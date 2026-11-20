<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voting_periods', function (Blueprint $table): void {
            $table->engine = 'InnoDB';

            $table->ulid('id')->primary();
            $table->string('name', 128);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('is_active')->default(false);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'ends_at'], 'voting_periods_active_window_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voting_periods');
    }
};
