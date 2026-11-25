<?php

declare(strict_types=1);

namespace SanctumManager;

use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\NewAccessToken;
use SanctumManager\Contracts\ScopeRegistryContract;
use SanctumManager\Contracts\TokenManagerContract;
use SanctumManager\DataTransferObjects\RotatedTokenResult;
use SanctumManager\DataTransferObjects\ScopeDefinition;
use SanctumManager\DataTransferObjects\ScopeGroup;
use SanctumManager\DataTransferObjects\TokenOptions;
use SanctumManager\Models\PersonalAccessToken;
use SanctumManager\Scopes\ScopeMatcher;

class SanctumManager
{
    public function __construct(
        protected ScopeRegistryContract $registry,
        protected ScopeMatcher $matcher,
        protected TokenManagerContract $tokenManager,
    ) {}

    /**
     * Register a scope definition in the ScopeRegistry.
     *
     * @param  array<int, string>  $implies
     */
    public function defineScope(
        string $name,
        string $description = '',
        ?string $group = null,
        array $implies = [],
        bool $isDefault = false,
        bool $isDeprecated = false,
    ): self {
        $this->registry->define(
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
     * Register a reusable scope group / preset in the ScopeRegistry.
     *
     * @param  array<int, string>  $scopes
     */
    public function defineGroup(string $name, array $scopes, string $description = ''): self
    {
        $this->registry->defineGroup($name, $scopes, $description);

        return $this;
    }

    /**
     * Retrieve all registered scope definitions.
     *
     * @return array<string, ScopeDefinition>
     */
    public function scopes(): array
    {
        return $this->registry->all();
    }

    /**
     * Retrieve all registered scope groups.
     *
     * @return array<string, ScopeGroup>
     */
    public function groups(): array
    {
        return $this->registry->groups();
    }

    /**
     * Determine whether a scope with the given name is registered.
     */
    public function hasScope(string $name): bool
    {
        return $this->registry->has($name);
    }

    /**
     * Retrieve a registered scope definition by name.
     */
    public function getScope(string $name): ?ScopeDefinition
    {
        return $this->registry->get($name);
    }

    /**
     * Retrieve the names of all scopes marked as default.
     *
     * @return array<int, string>
     */
    public function defaultScopes(): array
    {
        return $this->registry->defaultScopes();
    }

    /**
     * Expand groups and implied scopes recursively.
     *
     * @param  array<int, string>  $requested
     * @return array<int, string>
     */
    public function resolveScopes(array $requested, ?bool $strict = null): array
    {
        return $this->registry->resolveAndExpand($requested, $strict);
    }

    /**
     * Determine whether the granted scopes satisfy the required scope.
     *
     * @param  array<int, string>  $grantedScopes
     */
    public function matches(array $grantedScopes, string $requiredScope): bool
    {
        return $this->matcher->matches($grantedScopes, $requiredScope);
    }

    /**
     * Determine whether the granted scopes satisfy all required scopes.
     *
     * @param  array<int, string>  $grantedScopes
     * @param  array<int, string>  $requiredScopes
     */
    public function matchesAll(array $grantedScopes, array $requiredScopes): bool
    {
        return $this->matcher->matchesAll($grantedScopes, $requiredScopes);
    }

    /**
     * Determine whether the granted scopes satisfy at least one required scope.
     *
     * @param  array<int, string>  $grantedScopes
     * @param  array<int, string>  $requiredScopes
     */
    public function matchesAny(array $grantedScopes, array $requiredScopes): bool
    {
        return $this->matcher->matchesAny($grantedScopes, $requiredScopes);
    }

    /**
     * Issue a new personal access token for the given tokenable model.
     *
     * @param  array<int, string>  $scopes
     */
    public function issue(
        Model $tokenable,
        string $name,
        array $scopes = [],
        ?TokenOptions $options = null,
    ): NewAccessToken {
        return $this->tokenManager->issue($tokenable, $name, $scopes, $options);
    }

    /**
     * Revoke a specific personal access token.
     */
    public function revoke(PersonalAccessToken $token, ?string $reason = null): bool
    {
        return $this->tokenManager->revoke($token, $reason);
    }

    /**
     * Revoke all active tokens belonging to the given tokenable model.
     */
    public function revokeAll(Model $tokenable, ?string $reason = null): int
    {
        return $this->tokenManager->revokeAll($tokenable, $reason);
    }

    /**
     * Revoke all active tokens belonging to the given tokenable model that grant the specified scope.
     */
    public function revokeWithScope(Model $tokenable, string $scope, ?string $reason = null): int
    {
        return $this->tokenManager->revokeWithScope($tokenable, $scope, $reason);
    }

    /**
     * Rotate an existing personal access token with an optional grace period.
     */
    public function rotate(
        PersonalAccessToken $oldToken,
        ?int $gracePeriodMinutes = null,
        ?string $newName = null,
    ): RotatedTokenResult {
        return $this->tokenManager->rotate($oldToken, $gracePeriodMinutes, $newName);
    }

    /**
     * Access the underlying ScopeRegistry instance.
     */
    public function registry(): ScopeRegistryContract
    {
        return $this->registry;
    }

    /**
     * Access the underlying ScopeMatcher instance.
     */
    public function matcher(): ScopeMatcher
    {
        return $this->matcher;
    }

    /**
     * Access the underlying TokenManager instance.
     */
    public function tokenManager(): TokenManagerContract
    {
        return $this->tokenManager;
    }
}
