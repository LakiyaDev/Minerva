<?php

declare(strict_types=1);

use App\Modules\Articles\Database\Seeders\ArticleCategorySeeder;
use App\Modules\Articles\Enums\ArticleStatus;
use App\Modules\Articles\Models\Article;
use App\Modules\Articles\Models\ArticleCategory;
use App\Modules\Voting\Models\VotingPeriod;
use Illuminate\Support\Facades\Auth;

beforeEach(function (): void {
    seedIdentity();
    $this->seed(ArticleCategorySeeder::class);
});

it('requires administrator permissions and confirmed TOTP MFA for voting period management', function (): void {
    $participant = identityUser('participant');
    $this->withToken($participant->createToken('test')->plainTextToken)
        ->getJson('/api/v1/admin/voting-periods')
        ->assertForbidden();

    Auth::forgetGuards();

    $adminWithoutMfa = identityUser('administrator');
    $this->withToken($adminWithoutMfa->createToken('test')->plainTextToken)
        ->getJson('/api/v1/admin/voting-periods')
        ->assertStatus(403)
        ->assertJsonPath('code', 'mfa_enrollment_required');
});

it('allows an MFA-verified administrator to create, update, and activate voting periods while keeping only one active', function (): void {
    $admin = identityUser('administrator');
    enableTotp($admin);
    $token = $admin->createToken('test')->plainTextToken;

    $firstRes = $this->withToken($token)
        ->postJson('/api/v1/admin/voting-periods', [
            'name' => 'Round 1 Voting',
            'starts_at' => now()->subHour()->toIso8601String(),
            'ends_at' => now()->addDays(5)->toIso8601String(),
            'is_active' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Round 1 Voting')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.status', 'open');

    $firstId = (string) $firstRes->json('data.id');

    $secondRes = $this->withToken($token)
        ->postJson('/api/v1/admin/voting-periods', [
            'name' => 'Finalist Voting',
            'starts_at' => now()->addDays(6)->toIso8601String(),
            'ends_at' => now()->addDays(10)->toIso8601String(),
            'is_active' => false,
        ])
        ->assertCreated()
        ->assertJsonPath('data.is_active', false);

    $secondId = (string) $secondRes->json('data.id');

    // Activating second period deactivates the first
    $this->withToken($token)
        ->postJson('/api/v1/admin/voting-periods/'.$secondId.'/activate')
        ->assertOk()
        ->assertJsonPath('data.is_active', true);

    expect(VotingPeriod::query()->findOrFail($firstId)->is_active)->toBeFalse();
    expect(VotingPeriod::query()->findOrFail($secondId)->is_active)->toBeTrue();

    // Update name and timestamps cleanly
    $this->withToken($token)
        ->patchJson('/api/v1/admin/voting-periods/'.$secondId, [
            'name' => 'Grand Finale Voting',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Grand Finale Voting');

    $this->withToken($token)
        ->getJson('/api/v1/admin/voting-periods')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $admin->getKey(),
        'action' => 'voting_period.activated',
    ]);
});

it('rejects invalid voting period date ranges on store and update', function (): void {
    $admin = identityUser('administrator');
    enableTotp($admin);
    $token = $admin->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/admin/voting-periods', [
            'name' => 'Invalid Window',
            'starts_at' => now()->addDays(3)->toIso8601String(),
            'ends_at' => now()->addDay()->toIso8601String(),
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed');

    $period = VotingPeriod::factory()->create([
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDays(5),
        'created_by' => $admin->getKey(),
    ]);

    $this->withToken($token)
        ->patchJson('/api/v1/admin/voting-periods/'.$period->getKey(), [
            'ends_at' => now()->subDay()->toIso8601String(),
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed');
});

it('returns aggregated platform-wide voting statistics for administrators', function (): void {
    $admin = identityUser('administrator');
    enableTotp($admin);
    $adminToken = $admin->createToken('test')->plainTextToken;

    VotingPeriod::factory()->create(['created_by' => $admin->getKey()]);

    $ai = ArticleCategory::query()->where('slug', 'ai-in-medicine')->firstOrFail();
    $bio = ArticleCategory::query()->where('slug', 'bioinformatics')->firstOrFail();

    $a1 = Article::factory()->withStatus(ArticleStatus::Published)->create(['category_id' => $ai->getKey()]);
    $a2 = Article::factory()->withStatus(ArticleStatus::Published)->create(['category_id' => $bio->getKey()]);

    $p1 = identityUser('participant');
    $p2 = identityUser('participant');

    Auth::forgetGuards();
    $this->withToken($p1->createToken('test')->plainTextToken)
        ->postJson('/api/v1/articles/'.$a1->getKey().'/votes')
        ->assertCreated();
    Auth::forgetGuards();
    $this->withToken($p2->createToken('test')->plainTextToken)
        ->postJson('/api/v1/articles/'.$a1->getKey().'/votes')
        ->assertCreated();
    $this->withToken($p2->createToken('test')->plainTextToken)
        ->postJson('/api/v1/articles/'.$a2->getKey().'/votes')
        ->assertCreated();

    Auth::forgetGuards();
    $this->withToken($adminToken)
        ->getJson('/api/v1/admin/voting/statistics')
        ->assertOk()
        ->assertJsonPath('data.total_votes', 3)
        ->assertJsonPath('data.unique_voters', 2)
        ->assertJsonPath('data.published_articles_count', 2)
        ->assertJsonPath('data.top_articles.0.article_id', $a1->getKey())
        ->assertJsonPath('data.top_articles.0.vote_count', 2)
        ->assertJsonPath('data.by_category.0.votes', 2)
        ->assertJsonCount(14, 'data.daily_activity');
});
