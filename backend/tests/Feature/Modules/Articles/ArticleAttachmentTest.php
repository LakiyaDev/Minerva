<?php

declare(strict_types=1);

use App\Modules\Articles\Database\Seeders\ArticleCategorySeeder;
use App\Modules\Articles\Enums\ArticleStatus;
use App\Modules\Articles\Models\Article;
use App\Modules\Articles\Models\ArticleAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    seedIdentity();
    $this->seed(ArticleCategorySeeder::class);
    Storage::fake('local');
});

it('uploads a valid PDF manuscript for an author draft', function (): void {
    $publisher = identityUser('publisher');
    $article = Article::factory()->withStatus(ArticleStatus::Draft)->create([
        'author_id' => $publisher->getKey(),
    ]);

    Sanctum::actingAs($publisher);

    $file = UploadedFile::fake()->create('manuscript.pdf', 1024, 'application/pdf');

    $response = $this->postJson("/api/v1/publisher/articles/{$article->getKey()}/attachments", [
        'file' => $file,
        'kind' => 'manuscript',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.original_name', 'manuscript.pdf')
        ->assertJsonPath('data.kind', 'manuscript')
        ->assertJsonPath('data.mime_type', 'application/pdf');

    $attachment = ArticleAttachment::query()->firstOrFail();

    expect($attachment->article_id)->toBe($article->getKey())
        ->and($attachment->storage_disk)->toBe('local')
        ->and($response->json('data.download_url'))
        ->toBe("/api/v1/articles/{$article->getKey()}/attachments/{$attachment->getKey()}/download");

    Storage::disk('local')->assertExists($attachment->storage_key);
});

it('uploads a valid image figure for an author draft', function (): void {
    $publisher = identityUser('publisher');
    $article = Article::factory()->withStatus(ArticleStatus::Draft)->create([
        'author_id' => $publisher->getKey(),
    ]);

    Sanctum::actingAs($publisher);

    $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', true);
    $image = UploadedFile::fake()->createWithContent('figure-1.png', $pngBytes ?: '');

    $this->postJson("/api/v1/publisher/articles/{$article->getKey()}/attachments", [
        'file' => $image,
        'kind' => 'figure',
    ])
        ->assertCreated()
        ->assertJsonPath('data.original_name', 'figure-1.png')
        ->assertJsonPath('data.kind', 'figure');

    $attachment = ArticleAttachment::query()->firstOrFail();
    Storage::disk('local')->assertExists($attachment->storage_key);
});

it('rejects files larger than 10MB', function (): void {
    $publisher = identityUser('publisher');
    $article = Article::factory()->withStatus(ArticleStatus::Draft)->create([
        'author_id' => $publisher->getKey(),
    ]);

    Sanctum::actingAs($publisher);

    $oversized = UploadedFile::fake()->create('huge.pdf', 10241, 'application/pdf');

    $this->postJson("/api/v1/publisher/articles/{$article->getKey()}/attachments", [
        'file' => $oversized,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['file']);
});

it('rejects disallowed file extensions and MIME types', function (string $filename, string $mime): void {
    $publisher = identityUser('publisher');
    $article = Article::factory()->withStatus(ArticleStatus::Draft)->create([
        'author_id' => $publisher->getKey(),
    ]);

    Sanctum::actingAs($publisher);

    $badFile = UploadedFile::fake()->create($filename, 100, $mime);

    $this->postJson("/api/v1/publisher/articles/{$article->getKey()}/attachments", [
        'file' => $badFile,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['file']);
})->with([
    'shell script' => ['exploit.sh', 'application/x-sh'],
    'executable' => ['payload.exe', 'application/x-msdownload'],
    'php script' => ['shell.php', 'application/x-httpd-php'],
]);

it('forbids a non-owner from uploading an attachment to another author draft', function (): void {
    $owner = identityUser('publisher');
    $other = identityUser('publisher');
    $article = Article::factory()->withStatus(ArticleStatus::Draft)->create([
        'author_id' => $owner->getKey(),
    ]);

    Sanctum::actingAs($other);

    $file = UploadedFile::fake()->create('manuscript.pdf', 256, 'application/pdf');

    $this->postJson("/api/v1/publisher/articles/{$article->getKey()}/attachments", [
        'file' => $file,
    ])->assertForbidden();

    expect(ArticleAttachment::query()->count())->toBe(0);
});

it('forbids uploading attachments to unpublished or published articles without admin permission', function (ArticleStatus $status): void {
    $owner = identityUser('publisher');
    $article = Article::factory()->withStatus($status)->create([
        'author_id' => $owner->getKey(),
    ]);

    Sanctum::actingAs($owner);

    $file = UploadedFile::fake()->create('manuscript.pdf', 256, 'application/pdf');

    $this->postJson("/api/v1/publisher/articles/{$article->getKey()}/attachments", [
        'file' => $file,
    ])->assertForbidden();
})->with([
    'unpublished' => [ArticleStatus::Unpublished],
    'published' => [ArticleStatus::Published],
]);

it('allows the author to delete an attachment from storage and database', function (): void {
    $publisher = identityUser('publisher');
    $article = Article::factory()->withStatus(ArticleStatus::Draft)->create([
        'author_id' => $publisher->getKey(),
    ]);

    Sanctum::actingAs($publisher);

    $file = UploadedFile::fake()->create('supplement.pdf', 200, 'application/pdf');

    $created = $this->postJson("/api/v1/publisher/articles/{$article->getKey()}/attachments", [
        'file' => $file,
        'kind' => 'supplement',
    ])->assertCreated();

    $attachmentId = $created->json('data.id');
    $attachment = ArticleAttachment::query()->findOrFail($attachmentId);

    Storage::disk('local')->assertExists($attachment->storage_key);

    $this->deleteJson("/api/v1/publisher/articles/{$article->getKey()}/attachments/{$attachmentId}")
        ->assertNoContent();

    expect(ArticleAttachment::query()->find($attachmentId))->toBeNull();
    Storage::disk('local')->assertMissing($attachment->storage_key);
});

it('forbids a non-owner from deleting another author attachment', function (): void {
    $owner = identityUser('publisher');
    $other = identityUser('publisher');
    $article = Article::factory()->withStatus(ArticleStatus::Draft)->create([
        'author_id' => $owner->getKey(),
    ]);

    Sanctum::actingAs($owner);

    $file = UploadedFile::fake()->create('supplement.pdf', 200, 'application/pdf');
    $created = $this->postJson("/api/v1/publisher/articles/{$article->getKey()}/attachments", [
        'file' => $file,
    ])->assertCreated();

    $attachmentId = $created->json('data.id');

    Sanctum::actingAs($other);

    $this->deleteJson("/api/v1/publisher/articles/{$article->getKey()}/attachments/{$attachmentId}")
        ->assertForbidden();

    expect(ArticleAttachment::query()->find($attachmentId))->not->toBeNull();
});

it('allows an anonymous user to download an attachment of a published article', function (): void {
    $owner = identityUser('publisher');
    $article = Article::factory()->withStatus(ArticleStatus::Draft)->create([
        'author_id' => $owner->getKey(),
    ]);

    Sanctum::actingAs($owner);

    $file = UploadedFile::fake()->create('published-paper.pdf', 300, 'application/pdf');
    $created = $this->postJson("/api/v1/publisher/articles/{$article->getKey()}/attachments", [
        'file' => $file,
        'kind' => 'manuscript',
    ])->assertCreated();

    $article->update([
        'status' => ArticleStatus::Published,
        'published_at' => now(),
    ]);

    $this->app['auth']->forgetGuards();

    $downloadUrl = $created->json('data.download_url');

    $this->get($downloadUrl)
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('returns 404 when an anonymous user attempts to download an attachment of a draft article', function (): void {
    $owner = identityUser('publisher');
    $article = Article::factory()->withStatus(ArticleStatus::Draft)->create([
        'author_id' => $owner->getKey(),
    ]);

    Sanctum::actingAs($owner);

    $file = UploadedFile::fake()->create('draft-secret.pdf', 200, 'application/pdf');
    $created = $this->postJson("/api/v1/publisher/articles/{$article->getKey()}/attachments", [
        'file' => $file,
    ])->assertCreated();

    $this->app['auth']->forgetGuards();

    $this->get($created->json('data.download_url'))
        ->assertNotFound();
});

it('allows the author to download an attachment of their own draft', function (): void {
    $owner = identityUser('publisher');
    $article = Article::factory()->withStatus(ArticleStatus::Draft)->create([
        'author_id' => $owner->getKey(),
    ]);

    Sanctum::actingAs($owner);

    $file = UploadedFile::fake()->create('my-draft.pdf', 200, 'application/pdf');
    $created = $this->postJson("/api/v1/publisher/articles/{$article->getKey()}/attachments", [
        'file' => $file,
    ])->assertCreated();

    $this->get($created->json('data.download_url'))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});
