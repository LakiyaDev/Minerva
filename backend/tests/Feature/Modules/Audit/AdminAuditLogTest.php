<?php

declare(strict_types=1);

use App\Modules\Audit\Models\AuditLog;

beforeEach(function (): void {
    seedIdentity();
    $this->withHeader('Origin', 'http://localhost:5173');
});

it('allows an MFA-verified administrator with audit.view to list audit logs with pagination', function (): void {
    $admin = identityUser('administrator');
    enableTotp($admin);

    AuditLog::query()->create([
        'actor_id' => $admin->id,
        'action' => 'article.published',
        'subject_type' => 'App\Modules\Articles\Models\Article',
        'subject_id' => '01JABCDEF1234567890',
        'metadata' => ['title' => 'First Essay'],
        'created_at' => now()->subMinutes(10),
    ]);

    AuditLog::query()->create([
        'actor_id' => $admin->id,
        'action' => 'comment.removed',
        'subject_type' => 'App\Modules\Comments\Models\Comment',
        'subject_id' => '01JABCDEF9999999999',
        'metadata' => ['reason' => 'Spam content'],
        'created_at' => now()->subMinutes(5),
    ]);

    $response = $this->actingAs($admin)
        ->getJson('/api/v1/admin/audit-logs?per_page=10')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'action',
                    'actor' => ['id', 'name', 'email'],
                    'subject_type',
                    'subject_id',
                    'metadata',
                    'created_at',
                ],
            ],
            'meta' => [
                'current_page',
                'per_page',
                'total',
            ],
        ]);

    expect($response->json('meta.total'))->toBeGreaterThanOrEqual(2);
});

it('filters audit logs by action and action prefix wildcard', function (): void {
    $admin = identityUser('administrator');
    enableTotp($admin);

    AuditLog::query()->create([
        'actor_id' => $admin->id,
        'action' => 'voting.period.created',
        'created_at' => now()->subMinutes(2),
    ]);

    AuditLog::query()->create([
        'actor_id' => $admin->id,
        'action' => 'identity.role.granted',
        'created_at' => now()->subMinutes(1),
    ]);

    // Exact action filter
    $exact = $this->actingAs($admin)
        ->getJson('/api/v1/admin/audit-logs?action=voting.period.created')
        ->assertOk()
        ->json('data');

    expect($exact)->toHaveCount(1)
        ->and($exact[0]['action'])->toBe('voting.period.created');

    // Wildcard action filter
    $wildcard = $this->actingAs($admin)
        ->getJson('/api/v1/admin/audit-logs?action=voting.*')
        ->assertOk()
        ->json('data');

    expect($wildcard)->toHaveCount(1)
        ->and($wildcard[0]['action'])->toBe('voting.period.created');
});

it('filters audit logs by actor_id and search term', function (): void {
    $admin = identityUser('administrator');
    enableTotp($admin);

    $participant = identityUser('participant', ['name' => 'Charlie Voter', 'email' => 'charlie@example.com']);

    AuditLog::query()->create([
        'actor_id' => $participant->id,
        'action' => 'vote.cast',
        'subject_id' => '01JXYZ999',
        'created_at' => now()->subMinutes(3),
    ]);

    AuditLog::query()->create([
        'actor_id' => $admin->id,
        'action' => 'admin.login',
        'created_at' => now()->subMinutes(1),
    ]);

    // Filter by actor_id
    $byActor = $this->actingAs($admin)
        ->getJson('/api/v1/admin/audit-logs?actor_id='.$participant->id)
        ->assertOk()
        ->json('data');

    expect($byActor)->toHaveCount(1)
        ->and($byActor[0]['actor']['email'])->toBe('charlie@example.com');

    // Filter by search query matching actor name
    $bySearch = $this->actingAs($admin)
        ->getJson('/api/v1/admin/audit-logs?search=Charlie')
        ->assertOk()
        ->json('data');

    expect($bySearch)->toHaveCount(1)
        ->and($bySearch[0]['actor']['name'])->toBe('Charlie Voter');
});

it('filters audit logs by date range', function (): void {
    $admin = identityUser('administrator');
    enableTotp($admin);

    AuditLog::query()->create([
        'actor_id' => $admin->id,
        'action' => 'old.action',
        'created_at' => now()->subDays(5),
    ]);

    AuditLog::query()->create([
        'actor_id' => $admin->id,
        'action' => 'today.action',
        'created_at' => now(),
    ]);

    $response = $this->actingAs($admin)
        ->getJson('/api/v1/admin/audit-logs?date_from='.now()->subDay()->toDateString())
        ->assertOk()
        ->json('data');

    $actions = array_column($response, 'action');
    expect($actions)->toContain('today.action')
        ->not->toContain('old.action');
});

it('blocks non-staff users from accessing audit logs', function (): void {
    $participant = identityUser('participant');

    $this->actingAs($participant)
        ->getJson('/api/v1/admin/audit-logs')
        ->assertForbidden();
});

it('requires MFA enrolment before staff can access audit logs', function (): void {
    $admin = identityUser('administrator');
    // TOTP not enabled

    $this->actingAs($admin)
        ->getJson('/api/v1/admin/audit-logs')
        ->assertForbidden();
});

it('rejects unauthenticated requests to audit logs', function (): void {
    $this->getJson('/api/v1/admin/audit-logs')->assertUnauthorized();
});
