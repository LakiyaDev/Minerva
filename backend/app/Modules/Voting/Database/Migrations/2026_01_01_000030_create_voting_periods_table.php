<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

        // Schema v1.1 §4.3.1: the window must end after it starts. MySQL and
        // MariaDB enforce CHECK constraints natively (8.0.16+ / 10.2+).
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement(
                'ALTER TABLE voting_periods ADD CONSTRAINT voting_periods_ends_after_starts '
                .'CHECK (ends_at > starts_at)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('voting_periods');
    }
};
