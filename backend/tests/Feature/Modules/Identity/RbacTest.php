<?php

declare(strict_types=1);

use App\Modules\Identity\Exceptions\IdentityException;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\PublisherPermission;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\PermissionGrantService;
use App\Modules\Identity\Services\PermissionResolver;
use App\Modules\Identity\Services\PublisherPermissionService;
use App\Modules\Identity\Services\RoleService;
use App\Modules\Identity\Services\UserManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    seedIdentity();
});

it('resolves role permissions and grants publishers direct publishing', function (): void {
    $resolver = app(PermissionResolver::class);

    $participant = identityUser('participant');
    $publisher = identityUser('publisher');

    expect($resolver->for($participant))->toContain('participant.vote')
        ->and($resolver->for($participant))->not->toContain('article.create')
        ->and($resolver->for($publisher))->toContain('article.create')
        ->and($resolver->for($publisher))->toContain('article.publish_own')
        ->and($resolver->for($publisher))->not->toContain('article.unpublish');
});

it('grants publisher role and direct publishing permission to staff users', function (): void {
    $admin = identityUser('administrator');
    $publisher = identityUser('publisher');
    $request = Request::create('/api/v1/admin/users/'.$publisher->getKey().'/publisher-permission', 'POST');

    app(PublisherPermissionService::class)->grant($publisher, true, $admin, $request);

    expect($publisher->fresh()->hasPermission('article.publish_own'))->toBeTrue();

    app(PublisherPermissionService::class)->revoke($publisher->fresh(), $admin, $request);

    expect($publisher->fresh()->hasPermission('article.publish_own'))->toBeFalse();
});

it('removes the publisher role when the grant is revoked', function (): void {
    $admin = identityUser('administrator');
    $publisher = identityUser('publisher');
    $request = Request::create('/');

    app(PublisherPermissionService::class)->grant($publisher, true, $admin, $request);
    app(PublisherPermissionService::class)->revoke($publisher->fresh(), $admin, $request);

    expect($publisher->fresh()->hasRole('publisher'))->toBeFalse();
});

it('ignores expired direct permission grants', function (): void {
    $admin = identityUser('administrator');
    $participant = identityUser('participant');
    $request = Request::create('/');

    app(PermissionGrantService::class)->grant(
        $participant,
        'article.create',
        $admin,
        Carbon::now()->subMinute(),
        $request,
    );

    expect($participant->fresh()->hasPermission('article.create'))->toBeFalse();

    // Re-granting replaces the expired row.
    app(PermissionGrantService::class)->grant($participant, 'article.create', $admin, null, $request);

    expect($participant->fresh()->hasPermission('article.create'))->toBeTrue()
        ->and(Permission::query()->where('name', 'article.create')->exists())->toBeTrue();
});

it('retires the active publisher grant when the publisher role is revoked', function (): void {
    $admin = identityUser('administrator');
    $publisher = identityUser('publisher');
    $request = Request::create('/');

    app(PublisherPermissionService::class)->grant($publisher, true, $admin, $request);

    expect($publisher->fresh()->hasPermission('article.publish_own'))->toBeTrue();

    // Revoking the role (rather than the grant endpoint) must not leave a
    // dormant grant that would silently restore direct publishing later.
    app(RoleService::class)->revoke($publisher->fresh(), 'publisher', $admin, $request);

    $publisher->refresh();

    expect($publisher->hasRole('publisher'))->toBeFalse()
        ->and(PublisherPermission::query()
            ->where('user_id', $publisher->getKey())
            ->whereNull('revoked_at')
            ->exists())->toBeFalse();
});

it('refuses to grant a staff role to a Google-only reader', function (): void {
    $admin = identityUser('administrator');
    $reader = identityUser('participant', ['email' => 'reader@example.com']);

    expect(fn () => app(RoleService::class)->assign($reader, 'publisher', $admin, Request::create('/')))
        ->toThrow(IdentityException::class);

    expect($reader->fresh()->hasRole('publisher'))->toBeFalse();
});

it('prevents self role changes and last-administrator removal', function (): void {
    $admin = identityUser('administrator');
    $request = Request::create('/');

    // Self role change is always refused.
    expect(fn () => app(RoleService::class)->revoke($admin, 'administrator', $admin, $request))
        ->toThrow(IdentityException::class);

    // Semantics of the last-administrator guard: the *target* must be the
    // only active administrator for the revoke to be refused. Here the other
    // administrator is deactivated, so the actor is a deactivated
    // administrator and the target stays the only active one. Deactivation
    // itself is guarded by `UserManagementService`; this asserts the same
    // invariant holds when `revoke` is called directly on the last active
    // administrator.
    $otherAdmin = identityUser('administrator');
    $otherAdmin->forceFill(['is_active' => false])->save();

    expect(fn () => app(RoleService::class)->revoke($admin, 'administrator', $otherAdmin, $request))
        ->toThrow(IdentityException::class);
});

it('prevents deactivating the last active administrator', function (): void {
    $admin = identityUser('administrator');
    $otherAdmin = identityUser('administrator');
    $request = Request::create('/');

    // Two active administrators: deactivating one is allowed.
    app(UserManagementService::class)->setActive($admin, false, $otherAdmin, $request);

    expect($admin->fresh()->is_active)->toBeFalse();

    // The remaining administrator is now the last active one. The actor is an
    // already-inactive administrator so the service itself must refuse.
    $inactiveActor = identityUser('administrator');
    $inactiveActor->forceFill(['is_active' => false])->save();

    expect(fn () => app(UserManagementService::class)->setActive($otherAdmin->fresh(), false, $inactiveActor, $request))
        ->toThrow(IdentityException::class);
});

it('enforces permissions on admin routes and requires staff MFA', function (): void {
    $participant = identityUser('participant');
    $admin = identityUser('administrator');

    Sanctum::actingAs($participant);
    $this->getJson('/api/v1/admin/users')->assertForbidden();

    Sanctum::actingAs($admin);
    $this->getJson('/api/v1/admin/users')
        ->assertStatus(403)
        ->assertJsonPath('code', 'mfa_enrollment_required');

    enableTotp($admin);

    $this->getJson('/api/v1/admin/users')->assertOk();
});

it('returns the effective identity profile', function (): void {
    $participant = identityUser('participant', ['name' => 'Reader']);
    Sanctum::actingAs($participant);

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.name', 'Reader')
        ->assertJsonPath('data.capabilities.is_participant', true)
        ->assertJsonPath('data.capabilities.can_publish_directly', false)
        ->assertJsonStructure(['data' => ['permissions', 'roles', 'two_factor_enabled']]);
});

it('returns a request id on unauthenticated API errors', function (): void {
    $response = $this->getJson('/api/v1/me');

    $response->assertStatus(401)
        ->assertJsonPath('code', 'unauthenticated')
        ->assertJsonStructure(['message', 'code', 'request_id']);

    expect($response->headers->get('X-Request-ID'))->toBeString()->not->toBeEmpty();
});

it('updates the display name only', function (): void {
    $participant = identityUser('participant', ['name' => 'Old Name']);
    Sanctum::actingAs($participant);

    $this->patchJson('/api/v1/me', ['name' => 'New Name', 'email' => 'attacker@example.com'])
        ->assertOk()
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('data.email', $participant->email);

    expect(User::query()->find($participant->getKey())->email)->toBe($participant->email);
});
