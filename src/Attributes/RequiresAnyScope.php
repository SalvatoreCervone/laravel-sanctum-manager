<?php

declare(strict_types=1);

namespace SanctumManager\Attributes;

use Attribute;

/**
 * Requires the authenticated Sanctum token to possess AT LEAST ONE of the specified scopes.
 *
 * May be applied to a controller class, a controller action method, or repeated multiple times.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class RequiresAnyScope
{
    /**
     * @var array<int, string>
     */
    public readonly array $scopes;

    /**
     * @param  string|array<int, string>  ...$scopes
     */
    public function __construct(string|array ...$scopes)
    {
        $flattened = [];

        foreach ($scopes as $scope) {
            if (is_array($scope)) {
                foreach ($scope as $item) {
                    $flattened[] = $item;
                }
            } else {
                $flattened[] = $scope;
            }
        }

        $this->scopes = array_values(array_unique($flattened));
    }
}
