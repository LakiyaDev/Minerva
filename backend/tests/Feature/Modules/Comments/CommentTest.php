<?php

declare(strict_types=1);

use App\Modules\Articles\Database\Seeders\ArticleCategorySeeder;
use App\Modules\Articles\Enums\ArticleStatus;
use App\Modules\Articles\Models\Article;
use App\Modules\Comments\Enums\CommentStatus;
use App\Modules\Comments\Http\Resources\CommentResource;
use App\Modules\Comments\Models\Comment;
use App\Modules\Comments\Models\CommentModerationRecord;
use App\Modules\Identity\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    seedIdentity();
    $this->seed(ArticleCategorySeeder::class);
});

function actingAsCommentModerator(): User
{
    $admin = identityUser('administrator');
    enableTotp($admin);

    Sanctum::actingAs($admin->fresh());

    return $admin;
}

it('allows an authenticated participant to post a comment on a published article', function (): void {
    $participant = identityUser('participant');
    $article = Article::factory()->withStatus(ArticleStatus::Published)->create();

    Sanctum::actingAs($participant);

    $this->postJson("/api/v1/articles/{$article->getKey()}/comments", [
        'body' => 'Thoughtful methodology and very clear findings.',
    ])
        ->assertCreated()
        ->assertJsonPath('data.body', 'Thoughtful methodology and very clear findings.')
        ->assertJsonPath('data.status', 'visible')
        ->assertJsonPath('data.author.id', $participant->getKey());

    expect(Comment::query()->count())->toBe(1);
});

it('rejects posting a comment to a draft or pending article', function (ArticleStatus $status): void {
    $participant = identityUser('participant');
    $article = Article::factory()->withStatus($status)->create();

    Sanctum::actingAs($participant);

    $this->postJson("/api/v1/articles/{$article->getKey()}/comments", [
        'body' => 'Should not be allowed on non-published articles.',
    ])->assertNotFound();

    expect(Comment::query()->count())->toBe(0);
})->with([
    'draft' => [ArticleStatus::Draft],
    'unpublished' => [ArticleStatus::Unpublished],
]);

it('validates comment body length between 2 and 2000 characters', function (string $body): void {
    $participant = identityUser('participant');
    $article = Article::factory()->withStatus(ArticleStatus::Published)->create();

    Sanctum::actingAs($participant);

    $this->postJson("/api/v1/articles/{$article->getKey()}/comments", [
        'body' => $body,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['body']);
})->with([
    'too short' => ['A'],
    'too long' => [str_repeat('a', 2001)],
]);

it('rejects unauthenticated guests attempting to post a comment', function (): void {
    $article = Article::factory()->withStatus(ArticleStatus::Published)->create();

    $this->postJson("/api/v1/articles/{$article->getKey()}/comments", [
        'body' => 'Guest attempt.',
    ])->assertUnauthorized();
});

it('allows unauthenticated visitors to read comments on a published article', function (): void {
    $participant = identityUser('participant');
    $article = Article::factory()->withStatus(ArticleStatus::Published)->create();

    Comment::factory()->create([
        'article_id' => $article->getKey(),
        'user_id' => $participant->getKey(),
        'body' => 'Publicly readable comment.',
    ]);

    $this->getJson("/api/v1/articles/{$article->getKey()}/comments")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.body', 'Publicly readable comment.');
});

it('allows a comment author to delete their own comment', function (): void {
    $participant = identityUser('participant');
    $article = Article::factory()->withStatus(ArticleStatus::Published)->create();

    $comment = Comment::factory()->create([
        'article_id' => $article->getKey(),
        'user_id' => $participant->getKey(),
    ]);

    Sanctum::actingAs($participant);

    $this->deleteJson("/api/v1/comments/{$comment->getKey()}")
        ->assertOk()
        ->assertJsonPath('message', 'Comment deleted.');

    expect(Comment::query()->find($comment->getKey()))->toBeNull();
});

it('forbids a user from deleting another user comment', function (): void {
    $author = identityUser('participant');
    $other = identityUser('participant');
    $article = Article::factory()->withStatus(ArticleStatus::Published)->create();

    $comment = Comment::factory()->create([
        'article_id' => $article->getKey(),
        'user_id' => $author->getKey(),
    ]);

    Sanctum::actingAs($other);

    $this->deleteJson("/api/v1/comments/{$comment->getKey()}")
        ->assertForbidden();

    expect(Comment::query()->find($comment->getKey()))->not->toBeNull();
});

it('allows staff with comment.moderate permission to soft-remove a comment with a reason and records an audit trail', function (): void {
    $author = identityUser('participant');
    $article = Article::factory()->withStatus(ArticleStatus::Published)->create();

    $comment = Comment::factory()->create([
        'article_id' => $article->getKey(),
        'user_id' => $author->getKey(),
        'body' => 'Spam link http://example.invalid',
    ]);

    $moderator = actingAsCommentModerator();

    $this->postJson("/api/v1/admin/comments/{$comment->getKey()}/remove", [
        'reason' => 'Violates community guidelines: promotional spam.',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'removed')
        ->assertJsonPath('data.is_removed', true);

    $comment->refresh();
    expect($comment->status)->toBe(CommentStatus::Removed)
        ->and($comment->removed_by)->toBe($moderator->getKey())
        ->and($comment->removed_at)->not->toBeNull();

    $record = CommentModerationRecord::query()->where('comment_id', $comment->getKey())->first();
    expect($record)->not->toBeNull()
        ->and($record->acted_by)->toBe($moderator->getKey())
        ->and($record->action)->toBe('removed')
        ->and($record->reason)->toBe('Violates community guidelines: promotional spam.');
});

it('rejects moderation without a reason or with a reason shorter than 5 characters', function (string $reason): void {
    $author = identityUser('participant');
    $article = Article::factory()->withStatus(ArticleStatus::Published)->create();
    $comment = Comment::factory()->create([
        'article_id' => $article->getKey(),
        'user_id' => $author->getKey(),
    ]);

    actingAsCommentModerator();

    $this->postJson("/api/v1/admin/comments/{$comment->getKey()}/remove", [
        'reason' => $reason,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['reason']);
})->with([
    'empty' => [''],
    'too short' => ['spam'],
]);

it('masks soft-removed comment body on the public comments route', function (): void {
    $author = identityUser('participant');
    $moderator = identityUser('administrator');
    $article = Article::factory()->withStatus(ArticleStatus::Published)->create();

    Comment::factory()->removed($moderator)->create([
        'article_id' => $article->getKey(),
        'user_id' => $author->getKey(),
        'body' => 'Original offensive text.',
    ]);

    $this->getJson("/api/v1/articles/{$article->getKey()}/comments")
        ->assertOk()
        ->assertJsonPath('data.0.body', CommentResource::REMOVED_MASK)
        ->assertJsonPath('data.0.is_removed', true);
});

it('returns the authenticated user own comment history via /api/v1/me/comments', function (): void {
    $me = identityUser('participant');
    $other = identityUser('participant');
    $article = Article::factory()->withStatus(ArticleStatus::Published)->create();

    Comment::factory()->create([
        'article_id' => $article->getKey(),
        'user_id' => $me->getKey(),
        'body' => 'My own comment.',
    ]);

    Comment::factory()->create([
        'article_id' => $article->getKey(),
        'user_id' => $other->getKey(),
        'body' => 'Someone else comment.',
    ]);

    Sanctum::actingAs($me);

    $this->getJson('/api/v1/me/comments')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.body', 'My own comment.');
});
