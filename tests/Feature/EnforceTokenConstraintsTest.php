<?php

declare(strict_types=1);

namespace SanctumManager\Tests\Feature;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use SanctumManager\DataTransferObjects\TokenOptions;
use SanctumManager\Events\TokenIpDenied;
use SanctumManager\Facades\SanctumManager;
use SanctumManager\Models\PersonalAccessToken;
use SanctumManager\Tests\TestCase;

class EnforceTokenConstraintsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:sanctum', 'sanctum.constraints'])
            ->get('/test-constraints', static fn (): JsonResponse => response()->json(['ok' => true]));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_soft_revoked_token_returns_401(): void
    {
        config()->set('sanctum-manager.security.keep_revoked_tokens', true);

        $user = $this->createUser();
        $issued = SanctumManager::issue($user, 'Revocable Token', ['orders:read']);

        /** @var PersonalAccessToken $token */
        $token = $issued->accessToken;

        $this->withToken($issued->plainTextToken)
            ->getJson('/test-constraints')
            ->assertOk();

        SanctumManager::revoke($token, 'Compromised');
        $this->app['auth']->forgetGuards();

        $this->withToken($issued->plainTextToken)
            ->getJson('/test-constraints')
            ->assertStatus(401);
    }

    public function test_rotated_token_within_grace_period_succeeds_with_deprecation_headers_and_fails_after_grace(): void
    {
        $now = Carbon::parse('2026-06-01 12:00:00');
        Carbon::setTestNow($now);

        $user = $this->createUser();
        $issued = SanctumManager::issue($user, 'Rotatable Token', ['orders:read']);

        /** @var PersonalAccessToken $oldToken */
        $oldToken = $issued->accessToken;

        $rotated = SanctumManager::rotate($oldToken, gracePeriodMinutes: 30);

        $response = $this->withToken($issued->plainTextToken)
            ->getJson('/test-constraints');

        $response->assertOk()
            ->assertHeader('X-Sanctum-Token-Deprecated', 'true')
            ->assertHeader('X-Sanctum-Token-Grace-Expires', $rotated->graceExpiresAt->toIso8601String());

        // Advance past the 30-minute grace period
        Carbon::setTestNow($now->copy()->addMinutes(31));
        $this->app['auth']->forgetGuards();

        $this->withToken($issued->plainTextToken)
            ->getJson('/test-constraints')
            ->assertStatus(401);

        // New rotated token still works cleanly without deprecation headers
        $this->app['auth']->forgetGuards();

        $newResponse = $this->withToken($rotated->newToken->plainTextToken)
            ->getJson('/test-constraints');

        $newResponse->assertOk()
            ->assertHeaderMissing('X-Sanctum-Token-Deprecated')
            ->assertHeaderMissing('X-Sanctum-Token-Grace-Expires');
    }

    public function test_deprecated_scope_adds_deprecated_scope_header(): void
    {
        SanctumManager::defineScope(
            name: 'legacy:export',
            description: 'Legacy export endpoint',
            isDeprecated: true,
        );
        SanctumManager::defineScope(
            name: 'orders:read',
            description: 'Read orders',
        );

        $user = $this->createUser();
        $issued = SanctumManager::issue($user, 'Legacy Client', ['orders:read', 'legacy:export']);

        $this->withToken($issued->plainTextToken)
            ->getJson('/test-constraints')
            ->assertOk()
            ->assertHeader('X-Sanctum-Scope-Deprecated', 'legacy:export');
    }

    public function test_ip_and_cidr_restrictions_allow_matching_ip_and_deny_unauthorized_ip(): void
    {
        Event::fake([TokenIpDenied::class]);

        $user = $this->createUser();
        $issued = SanctumManager::issue(
            $user,
            'IP Restricted Token',
            ['orders:read'],
            TokenOptions::make()->allowIps(['10.0.0.0/24']),
        );

        /** @var PersonalAccessToken $token */
        $token = $issued->accessToken;

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
            ->withToken($issued->plainTextToken)
            ->getJson('/test-constraints')
            ->assertOk();

        Event::assertNotDispatched(TokenIpDenied::class);

        $this->app['auth']->forgetGuards();

        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.99'])
            ->withToken($issued->plainTextToken)
            ->getJson('/test-constraints')
            ->assertStatus(403);

        Event::assertDispatched(
            TokenIpDenied::class,
            static fn (TokenIpDenied $event): bool => $event->token->getKey() === $token->getKey()
                && $event->clientIp === '192.168.1.99',
        );
    }

    public function test_origin_and_referer_restrictions_allow_exact_and_wildcard_and_reject_others(): void
    {
        $user = $this->createUser();
        $issued = SanctumManager::issue(
            $user,
            'Origin Restricted Token',
            ['orders:read'],
            TokenOptions::make()->allowOrigins(['https://exact.acme.test', '*.example.com']),
        );

        // Exact Origin matches
        $this->withToken($issued->plainTextToken)
            ->withHeader('Origin', 'https://exact.acme.test')
            ->getJson('/test-constraints')
            ->assertOk();

        // Wildcard subdomain Origin matches
        $this->withToken($issued->plainTextToken)
            ->withHeader('Origin', 'https://app.example.com')
            ->getJson('/test-constraints')
            ->assertOk();

        // Deep wildcard subdomain via Referer header matches
        $this->withToken($issued->plainTextToken)
            ->withHeader('Referer', 'https://v2.portal.example.com/dashboard')
            ->getJson('/test-constraints')
            ->assertOk();

        // Unauthorized Origin returns 403
        $this->withToken($issued->plainTextToken)
            ->withHeader('Origin', 'https://evil.attacker.com')
            ->getJson('/test-constraints')
            ->assertStatus(403);

        // Missing Origin and Referer returns 403
        $this->withToken($issued->plainTextToken)
            ->getJson('/test-constraints')
            ->assertStatus(403);
    }

    public function test_max_uses_limit_enforced_in_zero_migration_mode(): void
    {
        $this->assertFalse(PersonalAccessToken::usesExtendedColumns());

        $user = $this->createUser();
        $issued = SanctumManager::issue(
            $user,
            'Limited Token Zero Migration',
            ['orders:read'],
            TokenOptions::make()->maxUses(2),
        );

        $this->withToken($issued->plainTextToken)
            ->getJson('/test-constraints')
            ->assertOk();

        $this->withToken($issued->plainTextToken)
            ->getJson('/test-constraints')
            ->assertOk();

        $this->withToken($issued->plainTextToken)
            ->getJson('/test-constraints')
            ->assertStatus(429);
    }

    public function test_max_uses_limit_enforced_in_extended_mode(): void
    {
        $this->enableExtendedMigrations();
        $this->assertTrue(PersonalAccessToken::usesExtendedColumns());

        $user = $this->createUser();
        $issued = SanctumManager::issue(
            $user,
            'Limited Token Extended',
            ['orders:read'],
            TokenOptions::make()->maxUses(2),
        );

        $this->withToken($issued->plainTextToken)
            ->getJson('/test-constraints')
            ->assertOk();

        $this->withToken($issued->plainTextToken)
            ->getJson('/test-constraints')
            ->assertOk();

        $this->withToken($issued->plainTextToken)
            ->getJson('/test-constraints')
            ->assertStatus(429);

        /** @var PersonalAccessToken $freshToken */
        $freshToken = $issued->accessToken->fresh();
        $this->assertSame(2, $freshToken->getUsesCount());
    }

    public function test_debounced_usage_logging_respects_update_interval_seconds(): void
    {
        config()->set('sanctum-manager.audit.update_interval_seconds', 300);

        $startTime = Carbon::parse('2026-06-01 10:00:00');
        Carbon::setTestNow($startTime);

        $user = $this->createUser();
        $issued = SanctumManager::issue($user, 'Debounced Token', ['orders:read']);

        // First request records last_used_at
        $this->withToken($issued->plainTextToken)
            ->getJson('/test-constraints')
            ->assertOk();

        /** @var PersonalAccessToken $tokenAfterFirst */
        $tokenAfterFirst = PersonalAccessToken::query()->findOrFail($issued->accessToken->getKey());
        $this->assertNotNull($tokenAfterFirst->last_used_at);
        $this->assertSame($startTime->toDateTimeString(), $tokenAfterFirst->last_used_at->toDateTimeString());

        // Second request within the 300-second debounce window does NOT update last_used_at
        Carbon::setTestNow($startTime->copy()->addSeconds(120));

        $this->withToken($issued->plainTextToken)
            ->getJson('/test-constraints')
            ->assertOk();

        /** @var PersonalAccessToken $tokenAfterSecond */
        $tokenAfterSecond = PersonalAccessToken::query()->findOrFail($issued->accessToken->getKey());
        $this->assertSame($startTime->toDateTimeString(), $tokenAfterSecond->last_used_at?->toDateTimeString());

        // Third request after the 300-second debounce window expires DOES update last_used_at
        $afterWindow = $startTime->copy()->addSeconds(301);
        Carbon::setTestNow($afterWindow);

        $this->withToken($issued->plainTextToken)
            ->getJson('/test-constraints')
            ->assertOk();

        /** @var PersonalAccessToken $tokenAfterThird */
        $tokenAfterThird = PersonalAccessToken::query()->findOrFail($issued->accessToken->getKey());
        $this->assertSame($afterWindow->toDateTimeString(), $tokenAfterThird->last_used_at?->toDateTimeString());
    }
}
