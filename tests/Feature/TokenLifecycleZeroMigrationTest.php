<?php

declare(strict_types=1);

namespace SanctumManager\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use SanctumManager\DataTransferObjects\TokenOptions;
use SanctumManager\Events\TokenIssued;
use SanctumManager\Events\TokenRevoked;
use SanctumManager\Events\TokenRotated;
use SanctumManager\Exceptions\TokenQuotaExceededException;
use SanctumManager\Facades\SanctumManager;
use SanctumManager\Models\PersonalAccessToken;
use SanctumManager\Tests\TestCase;

class TokenLifecycleZeroMigrationTest extends TestCase
{
    #[Test]
    public function it_uses_zero_migration_mode_by_default(): void
    {
        $this->assertFalse(PersonalAccessToken::usesExtendedColumns());
    }

    #[Test]
    public function it_issues_tokens_with_options_via_trait_and_facade_and_embeds_meta(): void
    {
        Event::fake([TokenIssued::class]);

        $user = $this->createUser();

        $options = TokenOptions::make()
            ->expiresInDays(7)
            ->allowIps(['192.168.1.1', '10.0.0.0/8'])
            ->allowOrigins(['https://app.example.com'])
            ->maxUses(50)
            ->withMetadata(['env' => 'staging', 'service' => 'billing'])
            ->withPrefix('sm_live_');

        $issued = $user->issueToken('CI Pipeline', ['orders:read', 'orders:write'], $options);

        /** @var PersonalAccessToken $token */
        $token = $issued->accessToken->fresh();

        $this->assertStringContainsString('|sm_live_', $issued->plainTextToken);
        $this->assertNotNull($token->expires_at);
        $this->assertTrue($token->expires_at->isFuture());
        $this->assertEqualsWithDelta(now()->addDays(7)->timestamp, $token->expires_at->timestamp, 5);

        // Verify __meta: is embedded inside abilities in zero-migration mode
        $rawAbilities = $token->abilities ?? [];
        $metaEntries = array_filter(
            $rawAbilities,
            static fn (string $item): bool => str_starts_with($item, PersonalAccessToken::META_PREFIX),
        );
        $this->assertCount(1, $metaEntries);

        // Effective scopes exclude __meta: and can() works cleanly
        $this->assertSame(['orders:read', 'orders:write'], $token->getEffectiveScopes());
        $this->assertTrue($token->can('orders:read'));
        $this->assertTrue($token->can('orders:write'));
        $this->assertFalse($token->can('orders:delete'));

        // Accessors decode embedded metadata transparently
        $this->assertSame(['192.168.1.1', '10.0.0.0/8'], $token->getAllowedIps());
        $this->assertSame(['https://app.example.com'], $token->getAllowedOrigins());
        $this->assertSame(50, $token->getMaxUses());
        $this->assertSame(['env' => 'staging', 'service' => 'billing'], $token->getMetadata());

        // Issuing via Facade also works
        $facadeIssued = SanctumManager::issue(
            $user,
            'Facade Token',
            ['users:read'],
            TokenOptions::make()->withPrefix('sm_test_'),
        );
        $this->assertStringContainsString('|sm_test_', $facadeIssued->plainTextToken);

        Event::assertDispatched(TokenIssued::class, 2);
        Event::assertDispatched(TokenIssued::class, static function (TokenIssued $event) use ($user, $token): bool {
            return $event->tokenable->is($user)
                && $event->accessToken->is($token)
                && $event->scopes === ['orders:read', 'orders:write'];
        });
    }

    #[Test]
    public function it_enforces_token_quota_with_throw_exception_strategy(): void
    {
        config()->set('sanctum-manager.tokens.max_tokens_per_user', 2);
        config()->set('sanctum-manager.tokens.quota_exceeded_strategy', 'throw_exception');

        $user = $this->createUser();
        $user->issueToken('Token 1', ['orders:read']);
        $user->issueToken('Token 2', ['orders:read']);

        $this->expectException(TokenQuotaExceededException::class);

        $user->issueToken('Token 3', ['orders:read']);
    }

    #[Test]
    public function it_enforces_token_quota_with_revoke_oldest_strategy(): void
    {
        config()->set('sanctum-manager.tokens.max_tokens_per_user', 2);
        config()->set('sanctum-manager.tokens.quota_exceeded_strategy', 'revoke_oldest');

        $user = $this->createUser();
        $oldest = $user->issueToken('Token 1', ['orders:read'])->accessToken;
        $second = $user->issueToken('Token 2', ['orders:read'])->accessToken;
        $newest = $user->issueToken('Token 3', ['orders:read'])->accessToken;

        $this->assertNull(PersonalAccessToken::query()->find($oldest->getKey()));
        $this->assertNotNull(PersonalAccessToken::query()->find($second->getKey()));
        $this->assertNotNull(PersonalAccessToken::query()->find($newest->getKey()));
        $this->assertSame(2, $user->activeTokens()->count());
    }

    #[Test]
    public function it_hard_deletes_revoked_tokens_by_default_and_dispatches_event(): void
    {
        Event::fake([TokenRevoked::class]);

        $user = $this->createUser();
        /** @var PersonalAccessToken $token */
        $token = $user->issueToken('Temp Token', ['orders:read'])->accessToken;

        $result = $user->revokeToken($token, 'Compromised');

        $this->assertTrue($result);
        $this->assertNull(PersonalAccessToken::query()->find($token->getKey()));

        Event::assertDispatched(TokenRevoked::class, static function (TokenRevoked $event) use ($user, $token): bool {
            return $event->tokenable !== null
                && $event->tokenable->is($user)
                && $event->token->is($token)
                && $event->reason === 'Compromised';
        });
    }

    #[Test]
    public function it_soft_revokes_tokens_when_keep_revoked_tokens_is_enabled(): void
    {
        config()->set('sanctum-manager.security.keep_revoked_tokens', true);

        $user = $this->createUser();
        /** @var PersonalAccessToken $token */
        $token = $user->issueToken('Audit Token', ['orders:read'])->accessToken;

        $result = $user->revokeToken($token, 'User logged out');

        $this->assertTrue($result);

        /** @var PersonalAccessToken|null $reloaded */
        $reloaded = PersonalAccessToken::query()->find($token->getKey());
        $this->assertNotNull($reloaded);
        $this->assertTrue($reloaded->isRevoked());
        $this->assertNotNull($reloaded->getRevokedAt());
        $this->assertSame('User logged out', $reloaded->getRevocationReason());
        $this->assertFalse($reloaded->isUsable());
        $this->assertSame(0, $user->activeTokens()->count());
        $this->assertSame(1, PersonalAccessToken::query()->revoked()->count());
    }

    #[Test]
    public function it_revokes_all_tokens_and_tokens_with_specific_scope(): void
    {
        $user = $this->createUser();
        $t1 = $user->issueToken('Orders Token', ['orders:read', 'orders:write'])->accessToken;
        $t2 = $user->issueToken('Wildcard Token', ['orders:*'])->accessToken;
        $t3 = $user->issueToken('Billing Token', ['billing:read'])->accessToken;

        $revokedByScope = $user->revokeTokensWithScope('orders:write', 'Scope removed');
        $this->assertSame(2, $revokedByScope);
        $this->assertNull(PersonalAccessToken::query()->find($t1->getKey()));
        $this->assertNull(PersonalAccessToken::query()->find($t2->getKey()));
        $this->assertNotNull(PersonalAccessToken::query()->find($t3->getKey()));

        $user->issueToken('Another Token', ['profile:read']);
        $revokedAll = $user->revokeAllTokens('Logout everywhere');
        $this->assertSame(2, $revokedAll);
        $this->assertSame(0, $user->activeTokens()->count());
    }

    #[Test]
    public function it_rotates_token_with_grace_period_and_clones_scopes_and_metadata(): void
    {
        Event::fake([TokenRotated::class]);

        $user = $this->createUser();
        $options = TokenOptions::make()
            ->expiresInDays(10)
            ->allowIps(['203.0.113.5'])
            ->allowOrigins(['https://portal.example.com'])
            ->maxUses(100)
            ->withMetadata(['team' => 'core']);

        /** @var PersonalAccessToken $oldToken */
        $oldToken = $user->issueToken('Primary Token', ['orders:read', 'orders:write'], $options)->accessToken;

        $result = $user->rotateToken($oldToken, graceMinutes: 45, newName: 'Primary Token v2');

        /** @var PersonalAccessToken $reloadedOld */
        $reloadedOld = $result->oldToken->fresh();
        /** @var PersonalAccessToken $newToken */
        $newToken = $result->newToken->accessToken->fresh();

        // Old token has native expires_at set to grace period and is marked in grace period
        $this->assertNotNull($reloadedOld->expires_at);
        $this->assertEqualsWithDelta(now()->addMinutes(45)->timestamp, $reloadedOld->expires_at->timestamp, 5);
        $this->assertTrue($reloadedOld->isInGracePeriod());
        $this->assertNotNull($reloadedOld->getGraceExpiresAt());
        $this->assertTrue($reloadedOld->isUsable());

        // New token clones scopes and metadata and references old token
        $this->assertSame('Primary Token v2', $newToken->name);
        $this->assertSame(['orders:read', 'orders:write'], $newToken->getEffectiveScopes());
        $this->assertSame(['203.0.113.5'], $newToken->getAllowedIps());
        $this->assertSame(['https://portal.example.com'], $newToken->getAllowedOrigins());
        $this->assertSame(100, $newToken->getMaxUses());
        $this->assertSame(['team' => 'core'], $newToken->getMetadata());
        $this->assertSame($reloadedOld->getKey(), $newToken->getMetaAttribute('rotated_from_id'));

        Event::assertDispatched(TokenRotated::class, static function (TokenRotated $event) use ($user, $reloadedOld, $newToken): bool {
            return $event->tokenable->is($user)
                && $event->oldToken->is($reloadedOld)
                && $event->newToken->is($newToken);
        });
    }

    #[Test]
    public function it_rotates_token_with_zero_grace_period(): void
    {
        $user = $this->createUser();
        /** @var PersonalAccessToken $oldToken */
        $oldToken = $user->issueToken('Immediate Rotate', ['orders:read'])->accessToken;
        $oldId = $oldToken->getKey();

        $result = SanctumManager::rotate($oldToken, gracePeriodMinutes: 0);

        // Hard deleted by default when keep_revoked_tokens is false
        $this->assertNull(PersonalAccessToken::query()->find($oldId));
        $this->assertNotNull(PersonalAccessToken::query()->find($result->newToken->accessToken->getKey()));

        // When keep_revoked_tokens is true, 0-grace rotation soft-revokes old token
        config()->set('sanctum-manager.security.keep_revoked_tokens', true);
        /** @var PersonalAccessToken $secondToken */
        $secondToken = $result->newToken->accessToken;
        $secondResult = SanctumManager::rotate($secondToken, gracePeriodMinutes: 0);

        /** @var PersonalAccessToken|null $softRevoked */
        $softRevoked = PersonalAccessToken::query()->find($secondToken->getKey());
        $this->assertNotNull($softRevoked);
        $this->assertTrue($softRevoked->isRevoked());
        $this->assertFalse($softRevoked->isInGracePeriod());
        $this->assertNotNull($secondResult->newToken->accessToken->fresh());
    }

    #[Test]
    public function it_caches_token_lookups_and_invalidates_cache_on_save_and_delete(): void
    {
        config()->set('sanctum-manager.cache.enabled', true);
        config()->set('sanctum-manager.cache.ttl_seconds', 120);

        $user = $this->createUser();
        $issued = $user->issueToken('Cached Token', ['orders:read']);
        $plainText = $issued->plainTextToken;
        /** @var PersonalAccessToken $token */
        $token = $issued->accessToken;
        $cacheKey = PersonalAccessToken::getCacheKey($token->getKey());

        $this->assertFalse(Cache::has($cacheKey));

        // First lookup populates the cache
        $found = PersonalAccessToken::findToken($plainText);
        $this->assertNotNull($found);
        $this->assertTrue($found->is($token));
        $this->assertTrue(Cache::has($cacheKey));

        // Invalid token string with same ID returns null
        $this->assertNull(PersonalAccessToken::findToken($token->getKey().'|wrong-secret'));

        // Saving the model automatically invalidates cache
        $token->name = 'Renamed Token';
        $token->save();
        $this->assertFalse(Cache::has($cacheKey));

        // Re-populate cache
        $reFound = PersonalAccessToken::findToken($plainText);
        $this->assertNotNull($reFound);
        $this->assertSame('Renamed Token', $reFound->name);
        $this->assertTrue(Cache::has($cacheKey));

        // Deleting the model automatically invalidates cache
        $token->delete();
        $this->assertFalse(Cache::has($cacheKey));
        $this->assertNull(PersonalAccessToken::findToken($plainText));
    }

    #[Test]
    public function it_marks_expired_tokens_correctly(): void
    {
        $user = $this->createUser();
        /** @var PersonalAccessToken $token */
        $token = $user->issueToken(
            'Expiring Soon',
            ['orders:read'],
            TokenOptions::make()->expiresAt(Carbon::now()->subMinute()),
        )->accessToken;

        $this->assertTrue($token->isExpired());
        $this->assertFalse($token->isUsable());
    }
}
