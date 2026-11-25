<?php

declare(strict_types=1);

namespace SanctumManager\Scopes;

use SanctumManager\Contracts\ScopeRegistryContract;

class ScopeMatcher
{
    public function __construct(
        protected ScopeRegistryContract $registry,
    ) {}

    /**
     * Determine whether the granted scopes satisfy the required scope.
     *
     * @param  array<int, string>  $grantedScopes
     */
    public function matches(array $grantedScopes, string $requiredScope): bool
    {
        $effectiveScopes = $this->registry->resolveAndExpand($grantedScopes, false);

        if (in_array('*', $effectiveScopes, true) || in_array($requiredScope, $effectiveScopes, true)) {
            return true;
        }

        foreach ($effectiveScopes as $granted) {
            if (! str_contains($granted, '*')) {
                continue;
            }

            if ($this->wildcardMatches($granted, $requiredScope)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the granted scopes satisfy all required scopes.
     *
     * @param  array<int, string>  $grantedScopes
     * @param  array<int, string>  $requiredScopes
     */
    public function matchesAll(array $grantedScopes, array $requiredScopes): bool
    {
        foreach ($requiredScopes as $requiredScope) {
            if (! $this->matches($grantedScopes, $requiredScope)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine whether the granted scopes satisfy at least one of the required scopes.
     *
     * @param  array<int, string>  $grantedScopes
     * @param  array<int, string>  $requiredScopes
     */
    public function matchesAny(array $grantedScopes, array $requiredScopes): bool
    {
        foreach ($requiredScopes as $requiredScope) {
            if ($this->matches($grantedScopes, $requiredScope)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match a wildcard scope pattern (e.g. "orders:*", "*:read", "orders:*:read") against a subject scope.
     */
    protected function wildcardMatches(string $pattern, string $subject): bool
    {
        $quoted = preg_quote($pattern, '/');
        $regex = '/^'.str_replace('\*', '(.+)', $quoted).'$/';

        return preg_match($regex, $subject) === 1;
    }
}
