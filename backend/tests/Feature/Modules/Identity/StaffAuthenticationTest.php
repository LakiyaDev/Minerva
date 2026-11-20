<?php

declare(strict_types=1);

use App\Modules\Identity\Exceptions\IdentityException;
use App\Modules\Identity\Models\LoginAttempt;
use App\Modules\Identity\Services\StaffAuthenticationService;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function (): void {
    seedIdentity();

    // Simulate the first-party SPA origin so Sanctum starts the session.
    $this->withHeader('Origin', 'http://localhost:5173');
});

it('challenges a staff member to enrol TOTP on first login', function (): void {
    $user = identityUser('administrator', [
        'email' => 'admin@example.com',
        'password' => 'Password!2345',
    ]);

    $response = $this->postJson('/api/v1/auth/staff/login', [
        'email' => 'ADMIN@example.com',
        'password' => 'Password!2345',
    ]);

    $response->assertStatus(202)
        ->assertJsonPath('data.status', 'mfa_setup_required');

    expect($response->json('data.challenge_token'))->toBeString()->not->toBeEmpty()
        ->and($user->fresh()->last_login_at)->toBeNull();

    $this->assertGuest();
});

it('requires the TOTP code after a correct password', function (): void {
    $user = identityUser('administrator', [
        'email' => 'admin@example.com',
        'password' => 'Password!2345',
    ]);

    $secret = enableTotp($user);

    $challenge = $this->postJson('/api/v1/auth/staff/login', [
        'email' => 'admin@example.com',
        'password' => 'Password!2345',
    ])->assertStatus(202)->json('data');

    expect($challenge['status'])->toBe('mfa_required');

    $code = (new Google2FA)->getCurrentOtp($secret);

    $this->postJson('/api/v1/auth/staff/mfa/verify', [
        'token' => $challenge['challenge_token'],
        'code' => $code,
    ])->assertOk()
        ->assertJsonPath('data.status', 'authenticated')
        ->assertJsonPath('data.user.email', 'admin@example.com');

    $this->assertAuthenticated();
});

it('rejects an invalid password with a generic error', function (): void {
    identityUser('publisher', [
        'email' => 'publisher@example.com',
        'password' => 'Password!2345',
    ]);

    $this->postJson('/api/v1/auth/staff/login', [
        'email' => 'publisher@example.com',
        'password' => 'wrong-password',
    ])->assertStatus(401)
        ->assertJsonPath('code', 'invalid_credentials');
});

it('never authenticates Google-only readers through the staff endpoint', function (): void {
    identityUser('participant', ['email' => 'reader@example.com']);

    $this->postJson('/api/v1/auth/staff/login', [
        'email' => 'reader@example.com',
        'password' => 'Password!2345',
    ])->assertStatus(401)
        ->assertJsonPath('code', 'invalid_credentials');
});

it('locks an account after repeated failures', function (): void {
    $user = identityUser('administrator', [
        'email' => 'admin@example.com',
        'password' => 'Password!2345',
    ]);

    $service = app(StaffAuthenticationService::class);
    $request = Request::create('/api/v1/auth/staff/login', 'POST');

    for ($attempt = 0; $attempt < 5; $attempt++) {
        try {
            $service->attempt('admin@example.com', 'wrong-password', $request);
        } catch (IdentityException) {
            // expected
        }
    }

    expect($user->fresh()->locked_until)->not->toBeNull()
        ->and(LoginAttempt::query()->where('email', 'admin@example.com')->count())->toBe(5);

    $this->expectException(IdentityException::class);

    $service->attempt('admin@example.com', 'Password!2345', $request);
});

it('rejects a deactivated staff account', function (): void {
    $user = identityUser('administrator', [
        'email' => 'admin@example.com',
        'password' => 'Password!2345',
    ]);
    enableTotp($user);

    $user->forceFill(['is_active' => false])->save();

    $this->postJson('/api/v1/auth/staff/login', [
        'email' => 'admin@example.com',
        'password' => 'Password!2345',
    ])->assertStatus(403)
        ->assertJsonPath('code', 'account_inactive');
});
