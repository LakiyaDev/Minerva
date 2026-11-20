<?php

declare(strict_types=1);

namespace App\Modules\Comments\Exceptions;

use App\Shared\Contracts\ApiError;
use RuntimeException;

class CommentException extends RuntimeException implements ApiError
{
    /**
     * @param  array<string, array<int, string>>  $errors
     * @param  array<string, string>  $headers
     */
    public function __construct(
        private readonly int $status,
        private readonly string $errorCode,
        string $message,
        private readonly array $errors = [],
        private readonly array $headers = [],
    ) {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function message(): string
    {
        return $this->getMessage();
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public static function articleNotPublished(): self
    {
        return new self(
            404,
            'article_not_found',
            'Comments are only available on published articles.',
        );
    }

    public static function alreadyRemoved(): self
    {
        return new self(
            409,
            'comment_already_removed',
            'This comment has already been removed.',
        );
    }
}
