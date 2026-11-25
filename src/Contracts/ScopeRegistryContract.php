<?php

declare(strict_types=1);

namespace SanctumManager\Contracts;

use SanctumManager\DataTransferObjects\ScopeDefinition;
use SanctumManager\DataTransferObjects\ScopeGroup;

interface ScopeRegistryContract
{
    /**
     * Define a scope in the registry.
     *
     * @param  array<int, string>  $implies
     */
    public function define(
        string $name,
        string $description = '',
        ?string $group = null,
        array $implies = [],
        bool $isDefault = false,
        bool $isDeprecated = false,
    ): self;

    /**
     * Define a reusable group (preset) of scopes.
     *
     * @param  array<int, string>  $scopes
     */
    public function defineGroup(
        string $name,
        array $scopes,
        string $description = '',
    ): self;

    /**
     * Determine whether a scope with the given name is registered.
     */
    public function has(string $name): bool;

    /**
     * Retrieve a registered scope definition by name.
     */
    public function get(string $name): ?ScopeDefinition;

    /**
     * Retrieve all registered scope definitions.
     *
     * @return array<string, ScopeDefinition>
     */
    public function all(): array;

    /**
     * Retrieve all registered scope groups.
     *
     * @return array<string, ScopeGroup>
     */
    public function groups(): array;

    /**
     * Retrieve the names of all scopes marked as default.
     *
     * @return array<int, string>
     */
    public function defaultScopes(): array;

    /**
     * Expand scope groups and implied scopes recursively, optionally validating
     * that all requested scopes exist in the registry.
     *
     * @param  array<int, string>  $requested
     * @return array<int, string>
     */
    public function resolveAndExpand(array $requested, ?bool $strict = null): array;

    /**
     * Clear all registered scopes and groups.
     */
    public function clear(): void;
}
