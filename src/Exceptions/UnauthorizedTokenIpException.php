<?php

declare(strict_types=1);

namespace SanctumManager\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class UnauthorizedTokenIpException extends HttpException
{
    /**
     * @param  array<string, string|array<int, string>>  $headers
     */
    public function __construct(
        string $message = 'Token is not authorized for this IP address.',
        ?Throwable $previous = null,
        array $headers = [],
        int $code = 0,
    ) {
        parent::__construct(403, $message, $previous, $headers, $code);
    }
}
