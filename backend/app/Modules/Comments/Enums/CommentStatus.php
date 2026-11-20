<?php

declare(strict_types=1);

namespace App\Modules\Comments\Enums;

enum CommentStatus: string
{
    case Visible = 'visible';
    case Removed = 'removed';

    public function label(): string
    {
        return match ($this) {
            self::Visible => 'Visible',
            self::Removed => 'Removed',
        };
    }
}
