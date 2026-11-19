<?php

declare(strict_types=1);

use App\Modules\Identity\Exceptions\IdentityException;
use App\Modules\Identity\Models\OAuthAccount;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\GoogleAuthenticationService;
use App\Modules\Identity\Services\OAuthStateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;

function verifiedGoogleUser(string $email = 'reader@example.com', string $id = 'google-123'): GoogleUser
{
    $user = new GoogleUser;
    $user->id = $id;
    $user->name = 'Reader Name';
    $user->email = $email;
    $user->avatar = 'https://example.com/avatar.png';
    $user->user = ['email_verified' => true];

    return $user;
}

beforeEach(function (): void {
    seedIdentity();
});

it('provisions a participant from a verified Google identity', function (): void {
    $service = app(GoogleAuthenticationService::class);

    $user = $service->authenticate(
        verifiedGoogleUser(),
        Request::create('/api/v1/auth/google/callback'),
    );

    expect($user->email)->toBe('reader@example.com')
        ->and($user->password)->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->hasRole('participant'))->toBeTrue()
        ->and($user->hasPermission('participant.vote'))->toBeTrue();

    $this->assertDatabaseHas('oauth_accounts', [
        'user_id' => $user->getKey(),
        'provider' => 'google',
        'provider_user_id' => 'google-123',
    ]);

    $this->assertDatabaseHas('audit_logs', ['action' => 'auth.google.signed_in']);
});

it('does not duplicate a user for the same Google identity', function (): void {
    $service = app(GoogleAuthenticationService::class);

    $first = $service->authenticate(verifiedGoogleUser(), Request::create('/'));
    $second = $service->authenticate(verifiedGoogleUser(), Request::create('/'));

    expect($second->getKey())->toBe($first->getKey());
    expect(OAuthAccount::query()->where('user_id', $first->getKey())->count())->toBe(1);
});

it('rejects an unverified Google email address', function (): void {
    $googleUser = verifiedGoogleUser();
    $googleUser->user = ['email_verified' => false];

    $this->expectException(IdentityException::class);

    app(GoogleAuthenticationService::class)->authenticate($googleUser, Request::create('/'));
});

it('never lets Google sign-in bypass staff MFA', function (): void {
    $staff = identityUser('administrator', [
        'email' => 'admin@example.com',
        'password' => 'Password!2345',
    ]);

    $this->expectException(IdentityException::class);

    app(GoogleAuthenticationService::class)->authenticate(
        verifiedGoogleUser($staff->email),
        Request::create('/'),
    );
});

it('completes the Google callback only with a valid single-use state', function (): void {
    $states = app(OAuthStateService::class);
    $state = $states->issue();

    $provider = Mockery::mock(Laravel\Socialite\Two\GoogleProvider::class);
    $provider->shouldReceive('stateless')->andReturnSelf();
    $provider->shouldReceive('user')->andReturn(verifiedGoogleUser());

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $response = $this->get('/api/v1/auth/google/callback?state='.urlencode($state).'&code=auth-code');

    $response->assertRedirect(config('identity.google.success_redirect').'?auth=success');

    expect(User::query()->where('email', 'reader@example.com')->exists())->toBeTrue();
});

it('rejects a replayed OAuth state', function (): void {
    $states = app(OAuthStateService::class);
    $state = $states->issue();
    $states->consume($state);

    $response = $this->get('/api/v1/auth/google/callback?state='.urlencode($state).'&code=auth-code');

    $response->assertRedirect(config('identity.google.failure_redirect').'?auth=error&reason=invalid_state');
});
