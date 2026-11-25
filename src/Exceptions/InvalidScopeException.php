<?php

declare(strict_types=1);

namespace SanctumManager\Exceptions;

use InvalidArgumentException;

class InvalidScopeException extends InvalidArgumentException
{
    public static function unregistered(string $scope): self
    {
        return new self("The scope [{$scope}] is not registered in the ScopeRegistry.");
    }
}
