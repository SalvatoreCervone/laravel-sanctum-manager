<?php

declare(strict_types=1);

namespace SanctumManager\Services;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\NewAccessToken;
use RuntimeException;
use SanctumManager\Contracts\ScopeRegistryContract;
use SanctumManager\Contracts\TokenManagerContract;
use SanctumManager\DataTransferObjects\RotatedTokenResult;
use SanctumManager\DataTransferObjects\TokenOptions;
use SanctumManager\Events\TokenIssued;
use SanctumManager\Events\TokenRevoked;
use SanctumManager\Events\TokenRotated;
use SanctumManager\Exceptions\TokenQuotaExceededException;
use SanctumManager\Exceptions\TokenRevokedException;
use SanctumManager\Models\PersonalAccessToken;

class TokenLifecycleService implements TokenManagerContract
{
    public function __construct(
        protected ScopeRegistryContract $registry,
    ) {}

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
        if ($scopes === []) {
            $defaults = $this->registry->defaultScopes();
            $scopes = $defaults !== [] ? $defaults : ['*'];
        }

        $resolvedScopes = $this->registry->resolveAndExpand($scopes);

        $this->enforceTokenQuota($tokenable);

        $expiresAt = $options?->expiresAt;

        if ($expiresAt === null) {
            $defaultExpiration = config('sanctum-manager.tokens.default_expiration_minutes');
            if (is_numeric($defaultExpiration) && (int) $defaultExpiration > 0) {
                $expiresAt = now()->addMinutes((int) $defaultExpiration);
            }
        }

        $prefix = $options !== null && $options->tokenPrefix !== null
            ? $options->tokenPrefix
            : (string) config('sanctum-manager.tokens.token_prefix', '');

        $newAccessToken = $this->createTokenForModel(
            tokenable: $tokenable,
            name: $name,
            abilities: $resolvedScopes,
            expiresAt: $expiresAt,
            prefix: $prefix,
        );

        /** @var PersonalAccessToken $token */
        $token = $newAccessToken->accessToken;

        $meta = [];
        if ($options !== null) {
            if ($options->allowedIps !== null && $options->allowedIps !== []) {
                $meta['allowed_ips'] = $options->allowedIps;
            }
            if ($options->allowedOrigins !== null && $options->allowedOrigins !== []) {
                $meta['allowed_origins'] = $options->allowedOrigins;
            }
            if ($options->maxUses !== null) {
                $meta['max_uses'] = $options->maxUses;
            }
            if ($options->metadata !== []) {
                $meta['metadata'] = $options->metadata;
            }
        }

        if ($meta !== []) {
            $token->setMetaAttributes($meta)->save();
        }

        event(new TokenIssued($tokenable, $token, $resolvedScopes));

        return $newAccessToken;
    }

    /**
     * Revoke a specific personal access token.
     */
    public function revoke(PersonalAccessToken $token, ?string $reason = null): bool
    {
        /** @var Model|null $tokenable */
        $tokenable = $token->tokenable;

        if ((bool) config('sanctum-manager.security.keep_revoked_tokens', false)) {
            $token->setMetaAttributes([
                'revoked_at' => now()->toIso8601String(),
                'revocation_reason' => $reason,
            ]);
            $token->expires_at = now();
            $result = $token->save();

            $key = $token->getKey();
            if (is_int($key) || is_string($key)) {
                PersonalAccessToken::forgetCache($key);
            }
        } else {
            $result = (bool) $token->delete();
        }

        event(new TokenRevoked($tokenable, $token, $reason));

        return $result;
    }

    /**
     * Revoke all active tokens belonging to the given tokenable model.
     */
    public function revokeAll(Model $tokenable, ?string $reason = null): int
    {
        $tokens = PersonalAccessToken::query()
            ->where('tokenable_type', $tokenable->getMorphClass())
            ->where('tokenable_id', $tokenable->getKey())
            ->active()
            ->get();

        $revokedCount = 0;

        foreach ($tokens as $token) {
            if ($this->revoke($token, $reason)) {
                $revokedCount++;
            }
        }

        return $revokedCount;
    }

    /**
     * Revoke all active tokens belonging to the given tokenable model that grant the specified scope.
     */
    public function revokeWithScope(Model $tokenable, string $scope, ?string $reason = null): int
    {
        $tokens = PersonalAccessToken::query()
            ->where('tokenable_type', $tokenable->getMorphClass())
            ->where('tokenable_id', $tokenable->getKey())
            ->active()
            ->get();

        $revokedCount = 0;

        foreach ($tokens as $token) {
            if ($token->can($scope) && $this->revoke($token, $reason)) {
                $revokedCount++;
            }
        }

        return $revokedCount;
    }

    /**
     * Rotate an existing personal access token with an optional grace period.
     */
    public function rotate(
        PersonalAccessToken $oldToken,
        ?int $gracePeriodMinutes = null,
        ?string $newName = null,
    ): RotatedTokenResult {
        if ($oldToken->isRevoked()) {
            throw new TokenRevokedException('Cannot rotate a token that has already been revoked.');
        }

        if ($oldToken->isExpired()) {
            throw new TokenRevokedException('Cannot rotate a token that has already expired.');
        }

        $graceMinutes = $gracePeriodMinutes ?? (int) config('sanctum-manager.rotation.grace_period_minutes', 30);

        return DB::transaction(function () use ($oldToken, $graceMinutes, $newName): RotatedTokenResult {
            /** @var Model|null $tokenable */
            $tokenable = $oldToken->tokenable;

            if ($tokenable === null) {
                throw new RuntimeException('Cannot rotate a token without an associated tokenable model.');
            }

            $graceExpiresAt = $graceMinutes > 0 ? now()->addMinutes($graceMinutes) : now();

            $newExpiresAt = $oldToken->expires_at !== null && $oldToken->created_at !== null
                ? now()->addSeconds((int) max(1, abs($oldToken->created_at->diffInSeconds($oldToken->expires_at))))
                : null;

            $effectiveScopes = $oldToken->getEffectiveScopes();
            $abilities = $effectiveScopes !== [] ? $effectiveScopes : ['*'];
            $prefix = (string) config('sanctum-manager.tokens.token_prefix', '');

            $newAccessToken = $this->createTokenForModel(
                tokenable: $tokenable,
                name: $newName ?? $oldToken->name,
                abilities: $abilities,
                expiresAt: $newExpiresAt,
                prefix: $prefix,
            );

            /** @var PersonalAccessToken $newModel */
            $newModel = $newAccessToken->accessToken;

            $allowedIps = $oldToken->getAllowedIps();
            $allowedOrigins = $oldToken->getAllowedOrigins();
            $metadata = $oldToken->getMetadata();

            $newModel->setMetaAttributes([
                'rotated_from_id' => $oldToken->getKey(),
                'allowed_ips' => $allowedIps !== [] ? $allowedIps : null,
                'allowed_origins' => $allowedOrigins !== [] ? $allowedOrigins : null,
                'max_uses' => $oldToken->getMaxUses(),
                'metadata' => $metadata !== [] ? $metadata : null,
            ])->save();

            if ($graceMinutes <= 0) {
                if ((bool) config('sanctum-manager.security.keep_revoked_tokens', false)) {
                    $oldToken->setMetaAttributes([
                        'revoked_at' => now()->toIso8601String(),
                        'revocation_reason' => 'Rotated immediately (0 grace period)',
                        'grace_expires_at' => now()->toIso8601String(),
                    ]);
                    $oldToken->expires_at = now();
                    $oldToken->save();
                } else {
                    $oldToken->delete();
                }
            } else {
                $oldToken->expires_at = $graceExpiresAt;
                $oldToken->setMetaAttributes([
                    'grace_expires_at' => $graceExpiresAt->toIso8601String(),
                ])->save();
            }

            event(new TokenRotated($tokenable, $oldToken, $newModel, $graceExpiresAt));

            return new RotatedTokenResult(
                newToken: $newAccessToken,
                oldToken: $oldToken,
                graceExpiresAt: $graceExpiresAt,
            );
        });
    }

    /**
     * Enforce the configured max_tokens_per_user quota for a tokenable model.
     *
     * @throws TokenQuotaExceededException
     */
    protected function enforceTokenQuota(Model $tokenable): void
    {
        $maxTokens = config('sanctum-manager.tokens.max_tokens_per_user');

        if (! is_numeric($maxTokens) || (int) $maxTokens <= 0) {
            return;
        }

        $limit = (int) $maxTokens;

        $activeTokens = PersonalAccessToken::query()
            ->where('tokenable_type', $tokenable->getMorphClass())
            ->where('tokenable_id', $tokenable->getKey())
            ->active()
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        if ($activeTokens->count() < $limit) {
            return;
        }

        $strategy = (string) config('sanctum-manager.tokens.quota_exceeded_strategy', 'throw_exception');

        if ($strategy === 'revoke_oldest') {
            $toRevokeCount = ($activeTokens->count() - $limit) + 1;

            foreach ($activeTokens->take($toRevokeCount) as $oldestToken) {
                $this->revoke($oldestToken, 'Token quota exceeded (revoked oldest)');
            }

            return;
        }

        throw new TokenQuotaExceededException(
            "Maximum number of active tokens ({$limit}) exceeded for this user.",
        );
    }

    /**
     * Create a new access token on the tokenable model, supporting optional custom prefixes.
     *
     * @param  array<int, string>  $abilities
     */
    protected function createTokenForModel(
        Model $tokenable,
        string $name,
        array $abilities,
        ?DateTimeInterface $expiresAt,
        string $prefix,
    ): NewAccessToken {
        if ($prefix !== '') {
            if (! method_exists($tokenable, 'tokens')) {
                throw new RuntimeException('Tokenable model must use HasApiTokens or ManagesSanctumTokens.');
            }

            $plainTextToken = $prefix.Str::random(40);

            /** @var MorphMany<PersonalAccessToken, Model> $relation */
            $relation = $tokenable->tokens();

            /** @var PersonalAccessToken $token */
            $token = $relation->create([
                'name' => $name,
                'token' => hash('sha256', $plainTextToken),
                'abilities' => $abilities,
                'expires_at' => $expiresAt,
            ]);

            return new NewAccessToken($token, $token->getKey().'|'.$plainTextToken);
        }

        if (! method_exists($tokenable, 'createToken')) {
            throw new RuntimeException('Tokenable model must use HasApiTokens or ManagesSanctumTokens.');
        }

        /** @var NewAccessToken $newAccessToken */
        $newAccessToken = $tokenable->createToken($name, $abilities, $expiresAt);

        return $newAccessToken;
    }
}
