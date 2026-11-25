<?php

declare(strict_types=1);

namespace SanctumManager\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use InvalidArgumentException;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\NewAccessToken;
use SanctumManager\Contracts\TokenManagerContract;
use SanctumManager\DataTransferObjects\RotatedTokenResult;
use SanctumManager\DataTransferObjects\TokenOptions;
use SanctumManager\Models\PersonalAccessToken;

/**
 * @mixin Model
 */
trait ManagesSanctumTokens
{
    /** @use HasApiTokens<PersonalAccessToken> */
    use HasApiTokens;

    /**
     * Issue a new personal access token managed by SanctumManager.
     *
     * @param  array<int, string>  $scopes
     */
    public function issueToken(
        string $name,
        array $scopes = [],
        ?TokenOptions $options = null,
    ): NewAccessToken {
        /** @var TokenManagerContract $manager */
        $manager = app(TokenManagerContract::class);

        return $manager->issue($this, $name, $scopes, $options);
    }

    /**
     * Revoke a specific token belonging to this model.
     */
    public function revokeToken(int|string|PersonalAccessToken $token, ?string $reason = null): bool
    {
        $resolved = $this->resolveOwnedToken($token);

        if ($resolved === null) {
            return false;
        }

        /** @var TokenManagerContract $manager */
        $manager = app(TokenManagerContract::class);

        return $manager->revoke($resolved, $reason);
    }

    /**
     * Revoke all active tokens belonging to this model.
     */
    public function revokeAllTokens(?string $reason = null): int
    {
        /** @var TokenManagerContract $manager */
        $manager = app(TokenManagerContract::class);

        return $manager->revokeAll($this, $reason);
    }

    /**
     * Revoke all active tokens belonging to this model that grant the specified scope.
     */
    public function revokeTokensWithScope(string $scope, ?string $reason = null): int
    {
        /** @var TokenManagerContract $manager */
        $manager = app(TokenManagerContract::class);

        return $manager->revokeWithScope($this, $scope, $reason);
    }

    /**
     * Rotate a token belonging to this model with an optional grace period.
     */
    public function rotateToken(
        int|string|PersonalAccessToken $token,
        ?int $graceMinutes = null,
        ?string $newName = null,
    ): RotatedTokenResult {
        $resolved = $this->resolveOwnedToken($token);

        if ($resolved === null) {
            throw new InvalidArgumentException('The specified token does not belong to this model or does not exist.');
        }

        /** @var TokenManagerContract $manager */
        $manager = app(TokenManagerContract::class);

        return $manager->rotate($resolved, $graceMinutes, $newName);
    }

    /**
     * Get the active (non-expired, non-revoked) personal access tokens belonging to this model.
     *
     * @return MorphMany<PersonalAccessToken, $this>
     */
    public function activeTokens(): MorphMany
    {
        /** @var MorphMany<PersonalAccessToken, $this> $relation */
        $relation = $this->tokens()->active();

        return $relation;
    }

    /**
     * Resolve a token instance or ID ensuring it belongs to this model.
     */
    protected function resolveOwnedToken(int|string|PersonalAccessToken $token): ?PersonalAccessToken
    {
        if ($token instanceof PersonalAccessToken) {
            if (
                $token->tokenable_type === $this->getMorphClass()
                && (string) $token->tokenable_id === (string) $this->getKey()
            ) {
                return $token;
            }

            return null;
        }

        /** @var PersonalAccessToken|null $found */
        $found = $this->tokens()->whereKey($token)->first();

        return $found;
    }
}
