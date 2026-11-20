<?php

declare(strict_types=1);

namespace App\Modules\Articles\Enums;

enum ArticleStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Unpublished = 'unpublished';

    /**
     * Only published articles are ever exposed by public routes.
     */
    public function isPublic(): bool
    {
        return $this === self::Published;
    }

    public function isEditableByOwner(): bool
    {
        return $this === self::Draft;
    }

    public function isPublished(): bool
    {
        return $this === self::Published;
    }

    public function isUnpublished(): bool
    {
        return $this === self::Unpublished;
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Unpublished => 'Unpublished',
        };
    }
}
