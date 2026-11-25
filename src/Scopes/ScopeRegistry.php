<?php

declare(strict_types=1);

namespace SanctumManager\Scopes;

use SanctumManager\Contracts\ScopeRegistryContract;
use SanctumManager\DataTransferObjects\ScopeDefinition;
use SanctumManager\DataTransferObjects\ScopeGroup;
use SanctumManager\Exceptions\InvalidScopeException;

class ScopeRegistry implements ScopeRegistryContract
{
    /** @var array<string, ScopeDefinition> */
    protected array $scopes = [];

    /** @var array<string, ScopeGroup> */
    protected array $groups = [];

    /**
     * Register a scope definition.
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
    ): self {
        $this->scopes[$name] = new ScopeDefinition(
            name: $name,
            description: $description,
            group: $group,
            implies: $implies,
            isDefault: $isDefault,
            isDeprecated: $isDeprecated,
        );

        return $this;
    }

    /**
     * Register a reusable scope group / preset.
     *
     * @param  array<int, string>  $scopes
     */
    public function defineGroup(string $name, array $scopes, string $description = ''): self
    {
        $this->groups[$name] = new ScopeGroup(
            name: $name,
            scopes: array_values($scopes),
            description: $description,
        );

        return $this;
    }

    /**
     * Determine whether a scope is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->scopes[$name]);
    }

    /**
     * Retrieve a registered scope definition by name.
     */
    public function get(string $name): ?ScopeDefinition
    {
        return $this->scopes[$name] ?? null;
    }

    /**
     * Retrieve all registered scope definitions.
     *
     * @return array<string, ScopeDefinition>
     */
    public function all(): array
    {
        return $this->scopes;
    }

    /**
     * Retrieve all registered scope groups.
     *
     * @return array<string, ScopeGroup>
     */
    public function groups(): array
    {
        return $this->groups;
    }

    /**
     * Retrieve the names of all scopes marked as default.
     *
     * @return array<int, string>
     */
    public function defaultScopes(): array
    {
        $defaults = [];

        foreach ($this->scopes as $definition) {
            if ($definition->isDefault) {
                $defaults[] = $definition->name;
            }
        }

        return $defaults;
    }

    /**
     * Remove a scope definition from the registry.
     */
    public function forget(string $name): self
    {
        unset($this->scopes[$name]);

        return $this;
    }

    /**
     * Remove a scope group from the registry.
     */
    public function forgetGroup(string $name): self
    {
        unset($this->groups[$name]);

        return $this;
    }

    /**
     * Clear all registered scopes and groups.
     */
    public function clear(): void
    {
        $this->scopes = [];
        $this->groups = [];
    }

    /**
     * Expand scope groups and recursively collect implied scopes with cycle protection.
     *
     * @param  array<int, string>  $requested
     * @return array<int, string>
     *
     * @throws InvalidScopeException
     */
    public function resolveAndExpand(array $requested, ?bool $strict = null): array
    {
        $strictMode = $strict ?? (bool) config('sanctum-manager.scopes.strict_validation', false);
        $expanded = [];

        foreach ($requested as $item) {
            $groupKey = ltrim($item, '@');

            if (isset($this->groups[$groupKey])) {
                foreach ($this->groups[$groupKey]->scopes as $groupScope) {
                    $this->collectImpliedScopes($groupScope, $expanded, $strictMode);
                }

                continue;
            }

            $this->collectImpliedScopes($item, $expanded, $strictMode);
        }

        return array_values(array_unique($expanded));
    }

    /**
     * Load scope definitions and groups from a configuration array.
     *
     * @param  array<string, mixed>  $config
     */
    public function loadFromConfig(array $config): self
    {
        $definitions = $config['definitions'] ?? [];

        if (is_array($definitions)) {
            foreach ($definitions as $name => $definition) {
                if (! is_string($name)) {
                    continue;
                }

                if (is_string($definition)) {
                    $this->define(name: $name, description: $definition);

                    continue;
                }

                if (is_array($definition)) {
                    $implies = [];
                    if (isset($definition['implies']) && is_array($definition['implies'])) {
                        $implies = array_values(array_filter(
                            $definition['implies'],
                            static fn (mixed $item): bool => is_string($item),
                        ));
                    }

                    $this->define(
                        name: $name,
                        description: isset($definition['description']) && is_string($definition['description'])
                            ? $definition['description']
                            : '',
                        group: isset($definition['group']) && is_string($definition['group'])
                            ? $definition['group']
                            : null,
                        implies: $implies,
                        isDefault: (bool) ($definition['default'] ?? $definition['is_default'] ?? false),
                        isDeprecated: (bool) ($definition['deprecated'] ?? $definition['is_deprecated'] ?? false),
                    );
                }
            }
        }

        $groups = $config['groups'] ?? [];

        if (is_array($groups)) {
            foreach ($groups as $groupName => $groupConfig) {
                if (! is_string($groupName) || ! is_array($groupConfig)) {
                    continue;
                }

                if (isset($groupConfig['scopes']) && is_array($groupConfig['scopes'])) {
                    $groupScopes = array_values(array_filter(
                        $groupConfig['scopes'],
                        static fn (mixed $item): bool => is_string($item),
                    ));
                    $description = isset($groupConfig['description']) && is_string($groupConfig['description'])
                        ? $groupConfig['description']
                        : '';

                    $this->defineGroup($groupName, $groupScopes, $description);

                    continue;
                }

                $groupScopes = array_values(array_filter(
                    $groupConfig,
                    static fn (mixed $item): bool => is_string($item),
                ));

                $this->defineGroup($groupName, $groupScopes);
            }
        }

        return $this;
    }

    /**
     * Recursively collect implied scopes while guarding against circular references.
     *
     * @param  array<int, string>  $visited
     *
     * @throws InvalidScopeException
     */
    protected function collectImpliedScopes(string $scope, array &$visited, bool $strict): void
    {
        if (in_array($scope, $visited, true)) {
            return;
        }

        if ($strict && $scope !== '*' && ! $this->isWildcard($scope) && ! isset($this->scopes[$scope])) {
            throw new InvalidScopeException("The scope [{$scope}] is not registered in the ScopeRegistry.");
        }

        $visited[] = $scope;

        if (isset($this->scopes[$scope])) {
            foreach ($this->scopes[$scope]->implies as $implied) {
                $this->collectImpliedScopes($implied, $visited, $strict);
            }
        }
    }

    /**
     * Determine whether the scope contains a wildcard character.
     */
    protected function isWildcard(string $scope): bool
    {
        return str_contains($scope, '*');
    }
}
