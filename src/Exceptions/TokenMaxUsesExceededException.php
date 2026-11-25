<?php

declare(strict_types=1);

namespace SanctumManager\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class TokenMaxUsesExceededException extends HttpException
{
    /**
     * @param  array<string, string|array<int, string>>  $headers
     */
    public function __construct(
        string $message = 'Token has reached its maximum number of uses.',
        ?Throwable $previous = null,
        array $headers = [],
        int $code = 0,
    ) {
        parent::__construct(429, $message, $previous, $headers, $code);
    }
}
