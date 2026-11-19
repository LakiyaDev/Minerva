<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_accounts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('provider_user_id', 191);
            $table->string('email')->nullable();
            $table->text('avatar_url')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_user_id'], 'oauth_accounts_provider_user_unique');
            $table->unique(['user_id', 'provider'], 'oauth_accounts_user_provider_unique');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_accounts');
    }
};
