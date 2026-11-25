<?php

declare(strict_types=1);

namespace SanctumManager\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class TokenQuotaExceededException extends HttpException
{
    /**
     * @param  array<string, string|array<int, string>>  $headers
     */
    public function __construct(
        string $message = 'Maximum number of active tokens exceeded.',
        ?Throwable $previous = null,
        array $headers = [],
        int $code = 0,
    ) {
        parent::__construct(422, $message, $previous, $headers, $code);
    }
}
