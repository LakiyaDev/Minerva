<?php

declare(strict_types=1);

use App\Modules\Identity\Exceptions\IdentityException;
use App\Modules\Identity\Models\StaffInvitation;
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

    $invitation = StaffInvitation::query()->create([
        'email' => 'late@example.com',
        'role' => 'publisher',
        'invited_by' => $admin->getKey(),
        'token_hash' => hash('sha256', $token),
        'expires_at' => now()->subMinute(),
    ]);

    $this->expectException(IdentityException::class);

    app(StaffInvitationService::class)->accept(
        $token,
        'Late Staff',
        'Password!2345',
        Request::create('/'),
    );
});

it('upgrades a Google-only reader to staff and keeps the participant role', function (): void {
    Notification::fake();

    $admin = identityUser('administrator');
    $reader = identityUser('participant', ['email' => 'reader@example.com']);

    $service = app(StaffInvitationService::class);
    $request = Request::create('/api/v1/admin/staff-invitations', 'POST');

    $service->invite($admin, $reader->email, 'publisher', $request);

    $token = null;

    Notification::assertSentOnDemand(
        StaffInvitationNotification::class,
        function (StaffInvitationNotification $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        },
    );

    $user = $service->accept(
        $token,
        'Reader Name',
        'Password!2345',
        Request::create('/api/v1/auth/staff/invitations/accept', 'POST'),
    );

    expect($user->getKey())->toBe($reader->getKey())
        ->and($user->fresh()->hasPasswordCredential())->toBeTrue()
        ->and($user->fresh()->hasRole('participant'))->toBeTrue()
        ->and($user->fresh()->hasRole('publisher'))->toBeTrue();

    expect(User::query()->where('email', 'reader@example.com')->count())->toBe(1);
});

it('does not invite an existing staff account or an assignable non-staff role', function (): void {
    $admin = identityUser('administrator');
    $existingStaff = identityUser('publisher', ['email' => 'existing@example.com']);
    $service = app(StaffInvitationService::class);
    $request = Request::create('/');

    expect(fn () => $service->invite($admin, $existingStaff->email, 'publisher', $request))
        ->toThrow(IdentityException::class);

    expect(fn () => $service->invite($admin, 'fresh@example.com', 'participant', $request))
        ->toThrow(IdentityException::class);

    expect(User::query()->where('email', 'fresh@example.com')->exists())->toBeFalse();
});
