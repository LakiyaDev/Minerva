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
        Schema::create('roles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name', 64)->unique();
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name', 128)->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table): void {
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignUlid('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->primary(['user_id', 'role_id']);
        });

        Schema::create('permission_role', function (Blueprint $table): void {
            $table->foreignUlid('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->foreignUlid('role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('user_permissions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->foreignUlid('granted_by')->nullable()->constrained('users')->nullOnDelete();
            // A direct grant may expire; expired rows are ignored at read time.
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'permission_id'], 'user_permissions_user_permission_unique');
            $table->index(['user_id', 'expires_at']);
        });

        Schema::create('publisher_permissions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('can_publish_directly')->default(false);
            $table->foreignUlid('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('granted_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at']);
        });

        // At most one *active* grant per user. MySQL cannot express a partial
        // unique index directly, so a generated column maps active rows to the
        // user id and inactive rows to NULL (NULLs are not unique-collisions).
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE publisher_permissions '
                .'ADD COLUMN active_user_id CHAR(26) '
                .'GENERATED ALWAYS AS (IF(revoked_at IS NULL, user_id, NULL)) STORED'
            );

            DB::statement(
                'CREATE UNIQUE INDEX publisher_permissions_one_active_per_user '
                .'ON publisher_permissions (active_user_id)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('publisher_permissions');
        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
