<?php

declare(strict_types=1);

namespace SanctumManager\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class TokenRevokedException extends HttpException
{
    /**
     * @param  array<string, string|array<int, string>>  $headers
     */
    public function __construct(
        string $message = 'Token has been revoked.',
        ?Throwable $previous = null,
        array $headers = [],
        int $code = 0,
    ) {
        parent::__construct(401, $message, $previous, $headers, $code);
    }
}
