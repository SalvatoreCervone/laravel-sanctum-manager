<?php

declare(strict_types=1);

namespace SanctumManager\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use SanctumManager\DataTransferObjects\TokenOptions;
use SanctumManager\Exceptions\TokenRevokedException;
use SanctumManager\Facades\SanctumManager;
use SanctumManager\Models\PersonalAccessToken;
use SanctumManager\Tests\TestCase;

class SecurityAndEdgeCasesTest extends TestCase
{
    #[Test]
    public function it_refuses_to_rotate_a_revoked_token(): void
    {
        config()->set('sanctum-manager.security.keep_revoked_tokens', true);

        $user = $this->createUser();
        /** @var PersonalAccessToken $token */
        $token = $user->issueToken('Revoked Token', ['orders:read'])->accessToken;
        $user->revokeToken($token, 'Compromised');

        $this->expectException(TokenRevokedException::class);

        $user->rotateToken($token->fresh());
    }

    #[Test]
    public function it_refuses_to_rotate_an_expired_token(): void
    {
        $user = $this->createUser();
        /** @var PersonalAccessToken $token */
        $token = $user->issueToken(
            'Expired Token',
            ['orders:read'],
            TokenOptions::make()->expiresAt(Carbon::now()->subMinute()),
        )->accessToken;

        $this->expectException(TokenRevokedException::class);

        $user->rotateToken($token);
    }

    #[Test]
    public function headless_store_endpoint_returns_422_for_invalid_scope_in_strict_mode(): void
    {
        config()->set('sanctum-manager.scopes.strict_validation', true);
        SanctumManager::defineScope('orders:read', 'Read orders');

        $user = $this->createUser();
        $authToken = $user->issueToken('Auth Token', ['orders:read']);

        $response = $this->withToken($authToken->plainTextToken)
            ->postJson('/api/sanctum-manager/tokens', [
                'name' => 'Bad Scope Token',
                'scopes' => ['nonexistent:scope'],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['scopes']);
    }

    #[Test]
    public function origin_restriction_enforces_scheme_when_explicitly_configured(): void
    {
        Route::middleware(['auth:sanctum', 'sanctum.constraints'])->get('/api/secure-origin', static fn () => response()->json(['ok' => true]));

        $user = $this->createUser();
        $issued = $user->issueToken(
            'HTTPS Only Token',
            ['orders:read'],
            TokenOptions::make()->allowOrigins(['https://app.example.com']),
        );

        $this->withToken($issued->plainTextToken)
            ->withHeader('Origin', 'https://app.example.com')
            ->getJson('/api/secure-origin')
            ->assertOk();

        $this->withToken($issued->plainTextToken)
            ->withHeader('Origin', 'http://app.example.com')
            ->getJson('/api/secure-origin')
            ->assertStatus(403);
    }

    #[Test]
    public function max_uses_counter_does_not_overshoot_in_zero_migration_mode(): void
    {
        Route::middleware(['auth:sanctum', 'sanctum.constraints'])->get('/api/ping-uses', static fn () => response()->json(['ok' => true]));

        $user = $this->createUser('uses@example.com');
        $limited = $user->issueToken('Limited', ['orders:read'], TokenOptions::make()->maxUses(2));

        $this->withToken($limited->plainTextToken)->getJson('/api/ping-uses')->assertOk();
        $this->withToken($limited->plainTextToken)->getJson('/api/ping-uses')->assertOk();
        $this->withToken($limited->plainTextToken)->getJson('/api/ping-uses')->assertStatus(429);

        /** @var PersonalAccessToken $limitedModel */
        $limitedModel = $limited->accessToken;
        $this->assertSame(2, $limitedModel->getUsesCount());
    }

    #[Test]
    public function extended_mode_tracks_uses_count_for_unlimited_tokens(): void
    {
        $this->enableExtendedMigrations();
        config()->set('sanctum-manager.audit.update_interval_seconds', 0);

        Route::middleware(['auth:sanctum', 'sanctum.constraints'])->get('/api/ping-uses-ext', static fn () => response()->json(['ok' => true]));

        $user = $this->createUser('uses-ext@example.com');
        $unlimited = $user->issueToken('Unlimited Extended', ['orders:read']);

        $this->withToken($unlimited->plainTextToken)->getJson('/api/ping-uses-ext')->assertOk();
        $this->withToken($unlimited->plainTextToken)->getJson('/api/ping-uses-ext')->assertOk();

        /** @var PersonalAccessToken $reloaded */
        $reloaded = $unlimited->accessToken->fresh();
        $this->assertSame(2, $reloaded->getUsesCount());
    }

    #[Test]
    public function prune_invalidates_token_cache_and_find_token_rejects_malformed_empty_segments(): void
    {
        config()->set('sanctum-manager.cache.enabled', true);

        $this->assertNull(PersonalAccessToken::findToken('|secret'));
        $this->assertNull(PersonalAccessToken::findToken('1|'));

        $user = $this->createUser();
        $expired = $user->issueToken(
            'Expired Cached',
            ['orders:read'],
            TokenOptions::make()->expiresAt(Carbon::now()->subDays(40)),
        );

        // Warm cache via findToken
        $found = PersonalAccessToken::findToken($expired->plainTextToken);
        $this->assertNotNull($found);
        $this->assertTrue(Cache::has(PersonalAccessToken::getCacheKey($expired->accessToken->getKey())));

        // Prune should delete token AND forget its cache key
        $this->artisan('sanctum-manager:prune', ['--days' => '30'])->assertExitCode(0);

        $this->assertFalse(Cache::has(PersonalAccessToken::getCacheKey($expired->accessToken->getKey())));
        $this->assertNull(PersonalAccessToken::findToken($expired->plainTextToken));
    }
}
