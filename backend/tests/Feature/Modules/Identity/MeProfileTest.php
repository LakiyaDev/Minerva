<?php

declare(strict_types=1);

use App\Modules\Audit\Models\AuditLog;

beforeEach(function (): void {
    seedIdentity();
    $this->withHeader('Origin', 'http://localhost:5173');
});

it('returns authenticated user profile with roles and capabilities', function (): void {
    $user = identityUser('participant', [
        'name' => 'Alice Participant',
        'email' => 'alice@example.com',
    ]);

    $response = $this->actingAs($user)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.name', 'Alice Participant')
        ->assertJsonPath('data.email', 'alice@example.com')
        ->assertJsonPath('data.capabilities.is_participant', true)
        ->assertJsonPath('data.capabilities.is_publisher', false);

    expect($response->json('data.roles'))->toContain('participant');
});

it('updates user display name and records an audit log', function (): void {
    $user = identityUser('participant', [
        'name' => 'Old Name',
        'email' => 'alice@example.com',
    ]);

    $response = $this->actingAs($user)
        ->patchJson('/api/v1/me', [
            'name' => 'New Name Verified',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'New Name Verified');

    expect($user->fresh()->name)->toBe('New Name Verified');

    $audit = AuditLog::query()
        ->where('action', 'identity.profile.updated')
        ->where('actor_id', $user->id)
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->subject_id)->toBe($user->id);
});

it('validates name constraints on profile update', function (): void {
    $user = identityUser('participant');

    $this->actingAs($user)
        ->patchJson('/api/v1/me', [
            'name' => 'A', // too short
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);

    $this->actingAs($user)
        ->patchJson('/api/v1/me', [
            'name' => str_repeat('X', 121), // too long
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('revokes other active sessions and records audit entry', function (): void {
    $user = identityUser('participant');

    $response = $this->actingAs($user)
        ->deleteJson('/api/v1/me/sessions')
        ->assertOk()
        ->assertJsonStructure(['data' => ['revoked_sessions']]);

    $audit = AuditLog::query()
        ->where('action', 'auth.sessions_revoked')
        ->where('actor_id', $user->id)
        ->first();

    expect($audit)->not->toBeNull();
});

it('rejects unauthenticated requests to me endpoints', function (): void {
    $this->getJson('/api/v1/me')->assertUnauthorized();
    $this->patchJson('/api/v1/me', ['name' => 'Valid Name'])->assertUnauthorized();
    $this->deleteJson('/api/v1/me/sessions')->assertUnauthorized();
});
