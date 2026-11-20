<?php

declare(strict_types=1);

namespace App\Modules\Articles\Exceptions;

use App\Modules\Articles\Enums\ArticleStatus;
use App\Shared\Contracts\ApiError;
use RuntimeException;

class ArticleException extends RuntimeException implements ApiError
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

    public static function notFound(): self
    {
        return new self(404, 'article_not_found', 'This article is unavailable to the public.');
    }

    public static function invalidTransition(ArticleStatus $from, ArticleStatus $to): self
    {
        return new self(
            409,
            'article_invalid_transition',
            "Articles cannot move from [{$from->value}] to [{$to->value}].",
            ['status' => ["Articles cannot move from [{$from->value}] to [{$to->value}]."]],
        );
    }

    public static function notEditable(ArticleStatus $status): self
    {
        return new self(
            409,
            'article_not_editable',
            "Only draft or rejected articles can be edited. This article is [{$status->value}].",
            ['status' => ["Only draft or rejected articles can be edited. This article is [{$status->value}]."]],
        );
    }
}
