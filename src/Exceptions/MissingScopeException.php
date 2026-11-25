<?php

declare(strict_types=1);

namespace SanctumManager\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class MissingScopeException extends HttpException
{
    /**
     * @var array<int, string>
     */
    public readonly array $scopes;

    /**
     * @param  array<int, string>  $scopes
     * @param  array<string, string|array<int, string>>  $headers
     */
    public function __construct(
        array $scopes = [],
        string $message = '',
        ?Throwable $previous = null,
        array $headers = [],
        int $code = 0,
    ) {
        $this->scopes = array_values($scopes);

        if ($message === '') {
            $message = $this->scopes !== []
                ? 'Invalid scope(s) provided. Missing: '.implode(', ', $this->scopes).'.'
                : 'Invalid scope(s) provided.';
        }

        parent::__construct(403, $message, $previous, $headers, $code);
    }
}
