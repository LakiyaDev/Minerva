<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Public website content tables — schema v1.1 §4.5 (FR-03, FR-05…FR-09).
 *
 * Every table carries `status` (draft / published / archived) and public
 * routes only ever return `published` rows (FR-25).
 */
return new class extends Migration
{
    public function up(): void
    {
        // §4.5.1 workshops — FR-03
        Schema::create('workshops', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('title', 200);
            $table->text('description');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            // Array of {name, title, affiliation, photo_url}.
            $table->json('speaker_details')->nullable();
            $table->string('status', 20)->default('draft');
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['status', 'starts_at'], 'workshops_status_starts_index');
        });

        // §4.5.2 workshop_resources — FR-03
        Schema::create('workshop_resources', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('workshop_id')->constrained('workshops')->cascadeOnDelete();
            $table->string('title', 200);
            $table->string('kind', 10)->default('link');
            // External URL or S3 object key — never a public bucket URL.
            $table->string('url_or_storage_key', 512);
            $table->string('mime_type', 127)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['workshop_id', 'sort_order'], 'workshop_resources_workshop_sort_index');
        });

        // §4.5.3 timeline_events — FR-05
        Schema::create('timeline_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->string('status', 20)->default('draft');
            $table->timestamps();

            $table->index(['status', 'starts_at'], 'timeline_events_status_starts_index');
        });

        // §4.5.4 sponsors — FR-06
        Schema::create('sponsors', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name', 200);
            $table->string('website_url', 500)->nullable();
            $table->string('logo_storage_key', 512)->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->string('status', 20)->default('draft');
            $table->timestamps();

            $table->index(['status', 'sort_order'], 'sponsors_status_sort_index');
        });

        // §4.5.5 committee_members — FR-07
        Schema::create('committee_members', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name', 200);
            $table->string('position', 150);
            $table->string('team', 150)->nullable();
            $table->string('photo_storage_key', 512)->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->string('status', 20)->default('draft');
            $table->timestamps();

            $table->index(['status', 'sort_order'], 'committee_members_status_sort_index');
            $table->index('team');
        });

        // §4.5.6 magazine_publications — FR-08, FR-09
        Schema::create('magazine_publications', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('cover_storage_key', 512)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();

            $table->index(['status', 'published_at'], 'magazine_publications_status_published_index');
        });

        // §4.5.7 e_magazine_files — FR-09
        Schema::create('e_magazine_files', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('magazine_publication_id')
                ->constrained('magazine_publications')
                ->cascadeOnDelete();
            // Private S3 object, delivered through short-lived signed URLs.
            $table->string('storage_key', 512);
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('size_bytes');
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('magazine_publication_id', 'e_magazine_files_publication_index');
        });

        // §4.5.3 CHECK: a timeline event cannot end before it starts. MySQL
        // and MariaDB enforce CHECK natively; SQLite skips it because the
        // grammar it accepts differs and the app validates the same rule.
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement(
                'ALTER TABLE timeline_events ADD CONSTRAINT timeline_events_ends_after_starts '
                .'CHECK (ends_at IS NULL OR ends_at >= starts_at)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('e_magazine_files');
        Schema::dropIfExists('magazine_publications');
        Schema::dropIfExists('committee_members');
        Schema::dropIfExists('sponsors');
        Schema::dropIfExists('timeline_events');
        Schema::dropIfExists('workshop_resources');
        Schema::dropIfExists('workshops');
    }
};
