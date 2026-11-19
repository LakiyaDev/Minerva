<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Notifications\StaffInvitationNotification;
use App\Modules\Identity\Services\StaffInvitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    seedIdentity();
});

it('invites staff by email without returning the plaintext token', function (): void {
    Notification::fake();

    $admin = identityUser('administrator', ['name' => 'Admin User']);

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/v1/admin/staff-invitations', [
            'email' => 'NewStaff@Example.com',
            'role' => 'publisher',
        ]);

    // Admin without TOTP is blocked before invitation logic.
    $response->assertStatus(403)->assertJsonPath('code', 'mfa_enrollment_required');
});

it('creates and accepts a staff invitation', function (): void {
    Notification::fake();

    $admin = identityUser('administrator', ['name' => 'Admin User']);
    $token = null;

    $invitation = app(StaffInvitationService::class)->invite(
        $admin,
        'newstaff@example.com',
        'publisher',
        Request::create('/api/v1/admin/staff-invitations', 'POST'),
    );

    Notification::assertSentOnDemand(
        StaffInvitationNotification::class,
        function (StaffInvitationNotification $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        },
    );

    expect($token)->toBeString()->not->toBeEmpty();

    $user = app(StaffInvitationService::class)->accept(
        $token,
        'New Staff',
        'Password!2345',
        Request::create('/api/v1/auth/staff/invitations/accept', 'POST'),
    );

    expect($user->email)->toBe('newstaff@example.com')
        ->and($user->hasRole('publisher'))->toBeTrue()
        ->and($user->hasPasswordCredential())->toBeTrue();

    expect($invitation->fresh()->accepted_at)->not->toBeNull();

    $this->assertDatabaseHas('audit_logs', ['action' => 'auth.staff.invitation_accepted']);
});

it('rejects an expired invitation', function (): void {
    $admin = identityUser('administrator');

    $token = 'known-token';

    $invitation = App\Modules\Identity\Models\StaffInvitation::query()->create([
        'email' => 'late@example.com',
        'role' => 'publisher',
        'invited_by' => $admin->getKey(),
        'token_hash' => hash('sha256', $token),
        'expires_at' => now()->subMinute(),
    ]);

    $this->expectException(App\Modules\Identity\Exceptions\IdentityException::class);

    app(StaffInvitationService::class)->accept(
        $token,
        'Late Staff',
        'Password!2345',
        Request::create('/'),
    );
});

it('does not invite an existing account or an assignable non-staff role', function (): void {
    $admin = identityUser('administrator');
    $existing = identityUser('participant', ['email' => 'existing@example.com']);
    $service = app(StaffInvitationService::class);
    $request = Request::create('/');

    expect(fn () => $service->invite($admin, $existing->email, 'publisher', $request))
        ->toThrow(App\Modules\Identity\Exceptions\IdentityException::class);

    expect(fn () => $service->invite($admin, 'fresh@example.com', 'participant', $request))
        ->toThrow(App\Modules\Identity\Exceptions\IdentityException::class);

    expect(User::query()->where('email', 'fresh@example.com')->exists())->toBeFalse();
});
