<?php

declare(strict_types=1);

namespace App\Modules\Identity\Console;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Shared\Support\EmailNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

final class CreateAdministratorCommand extends Command
{
    protected $signature = 'identity:create-admin {email : Administrator email address} {--name= : Display name}';

    protected $description = 'Create a bootstrap administrator account (password + TOTP enrolment on first login)';

    public function handle(): int
    {
        $email = EmailNormalizer::normalize((string) $this->argument('email'));

        if (User::query()->where('email', $email)->exists()) {
            $this->error('An account with this email already exists.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('Password');

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            [
                'email' => ['required', 'email:rfc'],
                'password' => ['required', 'string', Password::defaults()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $role = Role::query()->where('name', 'administrator')->first();

        if ($role === null) {
            $this->error('Roles are not seeded. Run: php artisan db:seed');

            return self::FAILURE;
        }

        $user = User::query()->create([
            'name' => (string) ($this->option('name') ?: Str::before($email, '@')),
            'email' => $email,
            'password' => $password,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $user->roles()->attach($role->getKey());

        $this->info('Administrator created.');
        $this->line('Sign in at POST /api/v1/auth/staff/login, then complete TOTP setup before using admin endpoints.');

        return self::SUCCESS;
    }
}
