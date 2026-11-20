<?php

declare(strict_types=1);

use App\Modules\Articles\Models\Article;
use App\Modules\Articles\Models\ArticleCategory;
use App\Modules\Identity\Models\User;
use App\Modules\Voting\Events\VotingResultsUpdated;
use App\Modules\Voting\Models\VotingPeriod;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    seedIdentity();
    $this->withHeader('Origin', 'http://localhost:5173');
    config(['broadcasting.default' => 'reverb']);
    require base_path('routes/channels.php');
});

it('authorizes a participant with participant.view_results on the voting results private channel', function (): void {
    $participant = identityUser('participant');
    $period = VotingPeriod::factory()->create();

    $response = $this->actingAs($participant)
        ->postJson('/broadcasting/auth', [
            'channel_name' => 'private-voting-results.'.$period->id,
            'socket_id' => '1234.5678',
        ]);

    $response->assertOk()
        ->assertJsonStructure(['auth']);
});

it('forbids a user without participant.view_results from authorizing on the private channel', function (): void {
    $guestUser = User::factory()->create([
        'email' => 'noperms@example.com',
        'is_active' => true,
    ]);

    $period = VotingPeriod::factory()->create();

    $this->actingAs($guestUser)
        ->postJson('/broadcasting/auth', [
            'channel_name' => 'private-voting-results.'.$period->id,
            'socket_id' => '1234.5678',
        ])
        ->assertForbidden();
});

it('forbids an inactive user from authorizing on the private channel', function (): void {
    $inactive = identityUser('participant', ['is_active' => false]);
    $period = VotingPeriod::factory()->create();

    $this->actingAs($inactive)
        ->postJson('/broadcasting/auth', [
            'channel_name' => 'private-voting-results.'.$period->id,
            'socket_id' => '1234.5678',
        ])
        ->assertForbidden();
});

it('broadcasts VotingResultsUpdated when a vote is cast', function (): void {
    Event::fake([VotingResultsUpdated::class]);

    $voter = identityUser('participant');
    $period = VotingPeriod::factory()->create([
        'is_active' => true,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
    ]);

    $category = ArticleCategory::create([
        'name' => 'Architecture',
        'slug' => 'architecture',
    ]);

    $article = Article::create([
        'title' => 'Sample Broadcast Article',
        'slug' => 'sample-broadcast-article',
        'abstract' => 'Sample abstract...',
        'body' => 'Content...',
        'author_id' => $voter->id,
        'category_id' => $category->id,
        'status' => 'published',
        'published_at' => now()->subDay(),
    ]);

    $this->actingAs($voter)
        ->postJson('/api/v1/articles/'.$article->id.'/votes')
        ->assertCreated();

    Event::assertDispatched(VotingResultsUpdated::class, function (VotingResultsUpdated $event) use ($period, $article) {
        return $event->periodId === $period->id
            && $event->articleId === $article->id
            && $event->voteCount === 1;
    });
});
