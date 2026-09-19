<?php

declare(strict_types=1);

namespace Aidat\Core\Exceptions;

use RuntimeException;

final class ValidationException extends RuntimeException
{
    /** @param array<string, string> $errors @param array<string, mixed> $input */
    public function __construct(public readonly array $errors, public readonly array $input = [], public readonly ?string $redirectTo = null)
    {
        parent::__construct('Doğrulama hatası');
    }
}
