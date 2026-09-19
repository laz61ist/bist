<?php

declare(strict_types=1);

namespace Aidat\Core\Exceptions;

final class AuthorizationException extends HttpException
{
    public function __construct(string $message = 'Bu işlem için yetkiniz yok.')
    {
        parent::__construct(403, $message);
    }
}
