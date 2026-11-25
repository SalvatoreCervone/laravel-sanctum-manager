<?php

declare(strict_types=1);

namespace SanctumManager\Http\Controllers;

use Illuminate\Http\JsonResponse;
use SanctumManager\Contracts\ScopeRegistryContract;
use SanctumManager\DataTransferObjects\ScopeDefinition;
use SanctumManager\DataTransferObjects\ScopeGroup;

class ScopeController
{
    /**
     * Return all registered scopes and scope groups.
     */
    public function index(ScopeRegistryContract $registry): JsonResponse
    {
        return response()->json([
            'scopes' => array_values(array_map(
                static fn (ScopeDefinition $scope): array => $scope->toArray(),
                $registry->all(),
            )),
            'groups' => array_values(array_map(
                static fn (ScopeGroup $group): array => $group->toArray(),
                $registry->groups(),
            )),
        ]);
    }
}
