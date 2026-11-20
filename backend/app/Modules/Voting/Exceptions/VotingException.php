<?php

declare(strict_types=1);

namespace App\Modules\Voting\Exceptions;

use App\Shared\Contracts\ApiError;
use RuntimeException;

class VotingException extends RuntimeException implements ApiError
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

    public static function alreadyVoted(): self
    {
        return new self(
            409,
            'vote_already_cast',
            'You have already voted on this article. Votes are final and cannot be changed.',
            ['article' => ['You have already voted on this article.']],
        );
    }

    public static function periodNotConfigured(): self
    {
        return new self(
            422,
            'voting_period_inactive',
            'There is no active voting period configured.',
            ['voting_period' => ['There is no active voting period configured.']],
        );
    }

    public static function periodNotOpen(string $state): self
    {
        $message = $state === 'upcoming'
            ? 'The voting period has not started yet.'
            : 'The voting period has closed.';

        return new self(
            422,
            'voting_period_inactive',
            $message,
            ['voting_period' => [$message]],
        );
    }

    public static function articleNotEligible(): self
    {
        return new self(
            404,
            'article_not_eligible',
            'This article is not eligible for voting.',
        );
    }

    public static function ineligibleVoter(): self
    {
        return new self(
            403,
            'voter_ineligible',
            'You are not eligible to cast votes.',
        );
    }
}
