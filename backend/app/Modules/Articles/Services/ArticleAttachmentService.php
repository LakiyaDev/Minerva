<?php

declare(strict_types=1);

namespace App\Modules\Articles\Services;

use App\Modules\Articles\Exceptions\ArticleException;
use App\Modules\Articles\Models\Article;
use App\Modules\Articles\Models\ArticleAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ArticleAttachmentService
{
    public function storeAttachment(
        Article $article,
        UploadedFile $file,
        string $kind = 'document',
    ): ArticleAttachment {
        $disk = (string) config('filesystems.default', 'local');
        $ulid = (string) Str::ulid();
        $extension = $file->extension() ?: $file->getClientOriginalExtension() ?: 'bin';
        $directory = "article-attachments/{$article->getKey()}";
        $filename = "{$ulid}.{$extension}";

        $storedPath = Storage::disk($disk)->putFileAs($directory, $file, $filename);

        if ($storedPath === false) {
            throw new RuntimeException('Unable to store article attachment.');
        }

        $originalName = mb_substr($file->getClientOriginalName(), 0, 255);
        $mimeType = $file->getMimeType() ?? $file->getClientMimeType() ?? 'application/octet-stream';

        return ArticleAttachment::create([
            'article_id' => $article->getKey(),
            'storage_disk' => $disk,
            'storage_key' => $storedPath,
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'size_bytes' => (int) ($file->getSize() ?: 0),
            'kind' => $kind,
        ]);
    }

    public function deleteAttachment(ArticleAttachment $attachment): void
    {
        Storage::disk($attachment->storage_disk)->delete($attachment->storage_key);
        $attachment->delete();
    }

    public function downloadResponse(ArticleAttachment $attachment): StreamedResponse
    {
        $disk = Storage::disk($attachment->storage_disk);

        if (! $disk->exists($attachment->storage_key)) {
            throw ArticleException::notFound();
        }

        return $disk->download(
            $attachment->storage_key,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type],
        );
    }
}
