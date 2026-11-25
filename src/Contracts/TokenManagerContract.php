<?php

declare(strict_types=1);

namespace SanctumManager\Contracts;

use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\NewAccessToken;
use SanctumManager\DataTransferObjects\RotatedTokenResult;
use SanctumManager\DataTransferObjects\TokenOptions;
use SanctumManager\Models\PersonalAccessToken;

interface TokenManagerContract
{
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
    ): NewAccessToken;

    /**
     * Revoke a specific personal access token.
     */
    public function revoke(PersonalAccessToken $token, ?string $reason = null): bool;

    /**
     * Revoke all active tokens belonging to the given tokenable model.
     */
    public function revokeAll(Model $tokenable, ?string $reason = null): int;

    /**
     * Revoke all tokens belonging to the given tokenable model that grant the specified scope.
     */
    public function revokeWithScope(Model $tokenable, string $scope, ?string $reason = null): int;

    /**
     * Rotate an existing personal access token with an optional grace period.
     */
    public function rotate(
        PersonalAccessToken $oldToken,
        ?int $gracePeriodMinutes = null,
        ?string $newName = null,
    ): RotatedTokenResult;
}
