<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

/**
 * Implemented by domain exceptions that know their public API representation.
 * The shared exception renderer never imports feature-module classes.
 */
interface ApiError
{
    public function status(): int;

    public function message(): string;

    public function errorCode(): string;

    /**
     * @return array<string, array<int, string>>
     */
    public function errors(): array;

    /**
     * @return array<string, string>
     */
    public function headers(): array;
}
