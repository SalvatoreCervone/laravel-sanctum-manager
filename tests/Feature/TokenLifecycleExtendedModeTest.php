<?php

declare(strict_types=1);

namespace SanctumManager\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use SanctumManager\Contracts\TokenUsageLoggerContract;
use SanctumManager\DataTransferObjects\TokenOptions;
use SanctumManager\Models\PersonalAccessToken;
use SanctumManager\Models\PersonalAccessTokenLog;
use SanctumManager\Tests\TestCase;

class TokenLifecycleExtendedModeTest extends TestCase
{
    #[Test]
    public function it_detects_extended_columns_in_explicit_and_auto_storage_modes(): void
    {
        // Before running extended migrations, 'auto' detects false
        config()->set('sanctum-manager.storage_mode', 'auto');
        PersonalAccessToken::resetExtendedColumnsCache();
        $this->assertFalse(PersonalAccessToken::usesExtendedColumns());

        // Enable extended migrations (sets mode to extended_columns)
        $this->enableExtendedMigrations();
        $this->assertTrue(PersonalAccessToken::usesExtendedColumns());

        // In 'auto' mode after migrations, it auto-detects true
        config()->set('sanctum-manager.storage_mode', 'auto');
        PersonalAccessToken::resetExtendedColumnsCache();
        $this->assertTrue(PersonalAccessToken::usesExtendedColumns());
    }

    #[Test]
    public function it_persists_extended_sql_columns_without_embedding_meta_in_abilities(): void
    {
        $this->enableExtendedMigrations();
        config()->set('sanctum-manager.security.keep_revoked_tokens', true);

        $user = $this->createUser();

        $options = TokenOptions::make()
            ->expiresInDays(5)
            ->allowIps(['198.51.100.10', '10.10.0.0/16'])
            ->allowOrigins(['https://admin.example.com'])
            ->maxUses(25)
            ->withMetadata(['department' => 'finance', 'tier' => 2]);

        /** @var PersonalAccessToken $token */
        $token = $user->issueToken('Extended Token', ['orders:read', 'billing:read'], $options)->accessToken->fresh();

        // Abilities remain completely clean without any __meta: entry
        $this->assertSame(['orders:read', 'billing:read'], $token->abilities);
        $this->assertSame(['orders:read', 'billing:read'], $token->getEffectiveScopes());

        // Verify raw SQL columns are populated directly
        $this->assertSame(['198.51.100.10', '10.10.0.0/16'], $token->allowed_ips);
        $this->assertSame(['https://admin.example.com'], $token->allowed_origins);
        $this->assertSame(25, $token->max_uses);
        $this->assertSame(['department' => 'finance', 'tier' => 2], $token->metadata);

        // Rotate and verify rotated_from_id and grace_expires_at SQL columns
        $rotated = $user->rotateToken($token, graceMinutes: 60);
        /** @var PersonalAccessToken $oldReloaded */
        $oldReloaded = $rotated->oldToken->fresh();
        /** @var PersonalAccessToken $newReloaded */
        $newReloaded = $rotated->newToken->accessToken->fresh();

        $this->assertNotNull($oldReloaded->grace_expires_at);
        $this->assertTrue($oldReloaded->isInGracePeriod());
        $this->assertSame($oldReloaded->getKey(), $newReloaded->rotated_from_id);
        $this->assertSame(['orders:read', 'billing:read'], $newReloaded->abilities);
        $this->assertSame(['198.51.100.10', '10.10.0.0/16'], $newReloaded->allowed_ips);
        $this->assertSame(['https://admin.example.com'], $newReloaded->allowed_origins);
        $this->assertSame(25, $newReloaded->max_uses);
        $this->assertSame(['department' => 'finance', 'tier' => 2], $newReloaded->metadata);

        // Soft-revoke and verify revoked_at and revocation_reason SQL columns
        $user->revokeToken($newReloaded, 'Scheduled rotation cleanup');
        /** @var PersonalAccessToken $revokedReloaded */
        $revokedReloaded = $newReloaded->fresh();

        $this->assertNotNull($revokedReloaded->revoked_at);
        $this->assertSame('Scheduled rotation cleanup', $revokedReloaded->revocation_reason);
        $this->assertTrue($revokedReloaded->isRevoked());
        $this->assertSame(['orders:read', 'billing:read'], $revokedReloaded->abilities);
    }

    #[Test]
    public function it_filters_tokens_using_query_scopes(): void
    {
        $this->enableExtendedMigrations();
        config()->set('sanctum-manager.security.keep_revoked_tokens', true);

        $user = $this->createUser();

        /** @var PersonalAccessToken $activeToken */
        $activeToken = $user->issueToken(
            'Active Token',
            ['orders:read', 'orders:write'],
            TokenOptions::make()->expiresInDays(3),
        )->accessToken;

        /** @var PersonalAccessToken $neverExpiresToken */
        $neverExpiresToken = $user->issueToken(
            'No Expiry Token',
            ['billing:read'],
        )->accessToken;

        /** @var PersonalAccessToken $expiredToken */
        $expiredToken = $user->issueToken(
            'Expired Token',
            ['orders:read'],
            TokenOptions::make()->expiresAt(Carbon::now()->subHour()),
        )->accessToken;

        /** @var PersonalAccessToken $revokedToken */
        $revokedToken = $user->issueToken(
            'Revoked Token',
            ['orders:write'],
        )->accessToken;
        $user->revokeToken($revokedToken, 'Manual revocation');

        $activeIds = PersonalAccessToken::query()->active()->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$activeToken->id, $neverExpiresToken->id], $activeIds);

        $expiredIds = PersonalAccessToken::query()->expired()->pluck('id')->all();
        // Soft-revoked token also sets expires_at = now(), so both expiredToken and revokedToken have expires_at <= now()
        $this->assertContains($expiredToken->id, $expiredIds);
        $this->assertNotContains($activeToken->id, $expiredIds);
        $this->assertNotContains($neverExpiresToken->id, $expiredIds);

        $revokedIds = PersonalAccessToken::query()->revoked()->pluck('id')->all();
        $this->assertSame([$revokedToken->id], $revokedIds);

        $ordersReadIds = PersonalAccessToken::query()->withScope('orders:read')->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$activeToken->id, $expiredToken->id], $ordersReadIds);

        $billingIds = PersonalAccessToken::query()->withScope('billing:read')->pluck('id')->all();
        $this->assertSame([$neverExpiresToken->id], $billingIds);
    }

    #[Test]
    public function it_records_database_audit_logs_when_enabled(): void
    {
        $this->enableExtendedMigrations();
        config()->set('sanctum-manager.audit.log_requests', true);
        config()->set('sanctum-manager.audit.driver', 'database');
        config()->set('sanctum-manager.audit.update_interval_seconds', 0);

        $user = $this->createUser();
        /** @var PersonalAccessToken $token */
        $token = $user->issueToken('Audited Token', ['orders:read'])->accessToken;

        $request = Request::create('/api/orders/42', 'GET', [], [], [], [
            'REMOTE_ADDR' => '192.0.2.55',
            'HTTP_USER_AGENT' => 'SanctumManagerTestClient/1.0',
        ]);

        /** @var TokenUsageLoggerContract $logger */
        $logger = app(TokenUsageLoggerContract::class);
        $logger->recordUsage($token, $request);

        /** @var PersonalAccessToken $reloaded */
        $reloaded = $token->fresh();
        $this->assertNotNull($reloaded->last_used_at);
        $this->assertSame('192.0.2.55', $reloaded->last_used_ip);
        $this->assertSame('SanctumManagerTestClient/1.0', $reloaded->last_used_user_agent);

        $this->assertSame(1, PersonalAccessTokenLog::query()->count());

        /** @var PersonalAccessTokenLog|null $log */
        $log = $reloaded->logs()->first();
        $this->assertNotNull($log);
        $this->assertSame($reloaded->id, $log->personal_access_token_id);
        $this->assertSame('192.0.2.55', $log->ip_address);
        $this->assertSame('GET', $log->method);
        $this->assertSame('api/orders/42', $log->path);
        $this->assertSame('SanctumManagerTestClient/1.0', $log->user_agent);
        $this->assertNotNull($log->created_at);
        $this->assertTrue($log->token->is($reloaded));
    }
}
