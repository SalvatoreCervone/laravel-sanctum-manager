<?php

declare(strict_types=1);

namespace SanctumManager\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use Laravel\Sanctum\NewAccessToken;
use SanctumManager\Contracts\ScopeRegistryContract;
use SanctumManager\Contracts\TokenManagerContract;
use SanctumManager\DataTransferObjects\RotatedTokenResult;
use SanctumManager\DataTransferObjects\ScopeDefinition;
use SanctumManager\DataTransferObjects\ScopeGroup;
use SanctumManager\DataTransferObjects\TokenOptions;
use SanctumManager\Models\PersonalAccessToken;
use SanctumManager\SanctumManager as SanctumManagerRoot;
use SanctumManager\Scopes\ScopeMatcher;

/**
 * @method static SanctumManagerRoot defineScope(string $name, string $description = '', ?string $group = null, array<int, string> $implies = [], bool $isDefault = false, bool $isDeprecated = false)
 * @method static SanctumManagerRoot defineGroup(string $name, array<int, string> $scopes, string $description = '')
 * @method static array<string, ScopeDefinition> scopes()
 * @method static array<string, ScopeGroup> groups()
 * @method static bool hasScope(string $name)
 * @method static ScopeDefinition|null getScope(string $name)
 * @method static array<int, string> defaultScopes()
 * @method static array<int, string> resolveScopes(array<int, string> $requested, ?bool $strict = null)
 * @method static bool matches(array<int, string> $grantedScopes, string $requiredScope)
 * @method static bool matchesAll(array<int, string> $grantedScopes, array<int, string> $requiredScopes)
 * @method static bool matchesAny(array<int, string> $grantedScopes, array<int, string> $requiredScopes)
 * @method static NewAccessToken issue(Model $tokenable, string $name, array<int, string> $scopes = [], ?TokenOptions $options = null)
 * @method static bool revoke(PersonalAccessToken $token, ?string $reason = null)
 * @method static int revokeAll(Model $tokenable, ?string $reason = null)
 * @method static int revokeWithScope(Model $tokenable, string $scope, ?string $reason = null)
 * @method static RotatedTokenResult rotate(PersonalAccessToken $oldToken, ?int $gracePeriodMinutes = null, ?string $newName = null)
 * @method static ScopeRegistryContract registry()
 * @method static ScopeMatcher matcher()
 * @method static TokenManagerContract tokenManager()
 *
 * @see SanctumManagerRoot
 */
class SanctumManager extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SanctumManagerRoot::class;
    }
}
