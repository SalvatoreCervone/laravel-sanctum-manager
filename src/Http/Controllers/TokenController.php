<?php

declare(strict_types=1);

namespace SanctumManager\Http\Controllers;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use SanctumManager\Contracts\TokenManagerContract;
use SanctumManager\DataTransferObjects\TokenOptions;
use SanctumManager\Exceptions\InvalidScopeException;
use SanctumManager\Http\Requests\RotateTokenRequest;
use SanctumManager\Http\Requests\StoreTokenRequest;
use SanctumManager\Models\PersonalAccessToken;

class TokenController
{
    /**
     * List all personal access tokens belonging to the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveAuthenticatedModel($request);

        $tokens = PersonalAccessToken::query()
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->getKey())
            ->latest('id')
            ->get();

        return response()->json([
            'tokens' => $tokens->map(fn (PersonalAccessToken $token): array => $this->serializeToken($token))->values()->all(),
        ]);
    }

    /**
     * Issue a new personal access token for the authenticated user.
     */
    public function store(StoreTokenRequest $request, TokenManagerContract $manager): JsonResponse
    {
        $user = $this->resolveAuthenticatedModel($request);

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $name = is_string($validated['name'] ?? null) ? $validated['name'] : '';

        $scopes = [];
        if (isset($validated['scopes']) && is_array($validated['scopes'])) {
            $scopes = array_values(array_filter(
                $validated['scopes'],
                static fn (mixed $scope): bool => is_string($scope),
            ));
        }

        $options = TokenOptions::make();

        if (isset($validated['expires_in_minutes']) && is_numeric($validated['expires_in_minutes'])) {
            $options->expiresInMinutes((int) $validated['expires_in_minutes']);
        }

        if (isset($validated['allowed_ips']) && is_array($validated['allowed_ips'])) {
            $options->allowIps(array_values(array_filter(
                $validated['allowed_ips'],
                static fn (mixed $ip): bool => is_string($ip),
            )));
        }

        if (isset($validated['allowed_origins']) && is_array($validated['allowed_origins'])) {
            $options->allowOrigins(array_values(array_filter(
                $validated['allowed_origins'],
                static fn (mixed $origin): bool => is_string($origin),
            )));
        }

        if (isset($validated['max_uses']) && is_numeric($validated['max_uses'])) {
            $options->maxUses((int) $validated['max_uses']);
        }

        if (isset($validated['metadata']) && is_array($validated['metadata'])) {
            /** @var array<string, mixed> $metadata */
            $metadata = $validated['metadata'];
            $options->withMetadata($metadata);
        }

        try {
            $newAccessToken = $manager->issue($user, $name, $scopes, $options);
        } catch (InvalidScopeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => [
                    'scopes' => [$exception->getMessage()],
                ],
            ], 422);
        }

        /** @var PersonalAccessToken $accessToken */
        $accessToken = $newAccessToken->accessToken;

        return response()->json([
            'plain_text_token' => $newAccessToken->plainTextToken,
            'token' => $this->serializeToken($accessToken),
        ], 201);
    }

    /**
     * Rotate an existing personal access token owned by the authenticated user.
     */
    public function rotate(
        RotateTokenRequest $request,
        int|string $tokenId,
        TokenManagerContract $manager,
    ): JsonResponse {
        $user = $this->resolveAuthenticatedModel($request);
        $token = $this->findOwnedToken($user, $tokenId);

        if ($token === null) {
            return response()->json(['message' => 'Token not found.'], 404);
        }

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        $gracePeriodMinutes = isset($validated['grace_period_minutes']) && is_numeric($validated['grace_period_minutes'])
            ? (int) $validated['grace_period_minutes']
            : null;

        $newName = isset($validated['name']) && is_string($validated['name'])
            ? $validated['name']
            : null;

        $result = $manager->rotate($token, $gracePeriodMinutes, $newName);

        /** @var PersonalAccessToken $newAccessToken */
        $newAccessToken = $result->newToken->accessToken;

        return response()->json([
            'plain_text_token' => $result->newToken->plainTextToken,
            'grace_expires_at' => $result->graceExpiresAt->toIso8601String(),
            'token' => $this->serializeToken($newAccessToken),
        ]);
    }

    /**
     * Revoke an existing personal access token owned by the authenticated user.
     */
    public function destroy(
        Request $request,
        int|string $tokenId,
        TokenManagerContract $manager,
    ): JsonResponse {
        $user = $this->resolveAuthenticatedModel($request);
        $token = $this->findOwnedToken($user, $tokenId);

        if ($token === null) {
            return response()->json(['message' => 'Token not found.'], 404);
        }

        $manager->revoke($token);

        return response()->json([
            'message' => 'Token revoked successfully.',
        ]);
    }

    /**
     * Resolve the authenticated user as an Eloquent model.
     *
     * @throws AuthenticationException
     */
    protected function resolveAuthenticatedModel(Request $request): Model
    {
        $user = $request->user();

        if (! $user instanceof Model) {
            throw new AuthenticationException;
        }

        return $user;
    }

    /**
     * Find a personal access token owned by the given user.
     */
    protected function findOwnedToken(Model $user, int|string $tokenId): ?PersonalAccessToken
    {
        /** @var PersonalAccessToken|null $token */
        $token = PersonalAccessToken::query()
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->getKey())
            ->whereKey($tokenId)
            ->first();

        return $token;
    }

    /**
     * Serialize a personal access token without exposing the hashed secret.
     *
     * @return array{
     *     id: mixed,
     *     name: string,
     *     scopes: array<int, string>,
     *     last_used_at: ?string,
     *     expires_at: ?string,
     *     revoked_at: ?string,
     *     created_at: ?string,
     *     allowed_ips: array<int, string>,
     *     allowed_origins: array<int, string>,
     *     max_uses: ?int,
     *     uses_count: int,
     *     metadata: array<string, mixed>
     * }
     */
    protected function serializeToken(PersonalAccessToken $token): array
    {
        return [
            'id' => $token->getKey(),
            'name' => $token->name,
            'scopes' => $token->getEffectiveScopes(),
            'last_used_at' => $token->last_used_at?->toIso8601String(),
            'expires_at' => $token->expires_at?->toIso8601String(),
            'revoked_at' => $token->getRevokedAt()?->toIso8601String(),
            'created_at' => $token->created_at?->toIso8601String(),
            'allowed_ips' => $token->getAllowedIps(),
            'allowed_origins' => $token->getAllowedOrigins(),
            'max_uses' => $token->getMaxUses(),
            'uses_count' => $token->getUsesCount(),
            'metadata' => $token->getMetadata(),
        ];
    }
}
