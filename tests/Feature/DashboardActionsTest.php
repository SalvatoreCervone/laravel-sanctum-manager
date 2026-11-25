<?php

declare(strict_types=1);

namespace SanctumManager\Tests\Feature;

use Illuminate\Support\Facades\Mail;
use SanctumManager\Contracts\ScopeRegistryContract;
use SanctumManager\Contracts\TokenManagerContract;
use SanctumManager\DataTransferObjects\TokenOptions;
use SanctumManager\Mail\TestNotificationMail;
use SanctumManager\Models\PersonalAccessToken;
use SanctumManager\Models\PersonalAccessTokenLog;
use SanctumManager\Tests\Fixtures\User;
use SanctumManager\Tests\TestCase;

class DashboardActionsTest extends TestCase
{
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        config()->set('sanctum-manager.dashboard.allowed_emails', ['admin@example.com']);

        $this->admin = $this->createUser('admin@example.com', 'Dashboard Admin');
    }

    public function test_index_renders_tokens_kpis_search_and_status_filters(): void
    {
        config()->set('sanctum-manager.security.keep_revoked_tokens', true);

        /** @var TokenManagerContract $manager */
        $manager = app(TokenManagerContract::class);

        $activeNew = $manager->issue($this->admin, 'Active Service Token', ['orders:read']);
        $graceTarget = $manager->issue($this->admin, 'Grace Period Token', ['orders:read']);
        $expiredNew = $manager->issue(
            $this->admin,
            'Expired Legacy Token',
            ['orders:read'],
            TokenOptions::make()->expiresAt(now()->subHour()),
        );
        $revokedNew = $manager->issue($this->admin, 'Revoked Compromised Token', ['orders:read']);

        /** @var PersonalAccessToken $graceToken */
        $graceToken = $graceTarget->accessToken;
        $manager->rotate($graceToken, 45, 'Rotated Replacement Token');

        /** @var PersonalAccessToken $revokedToken */
        $revokedToken = $revokedNew->accessToken;
        $manager->revoke($revokedToken, 'Compromised key');

        // Default 'all' view renders tokens and KPIs
        $response = $this->actingAs($this->admin)->get('/sanctum-manager');
        $response->assertOk()
            ->assertSee('Active Service Token')
            ->assertSee('Grace Period Token')
            ->assertSee('Rotated Replacement Token')
            ->assertSee('Expired Legacy Token')
            ->assertSee('Revoked Compromised Token');

        // Search by name
        $this->actingAs($this->admin)
            ->get('/sanctum-manager?search=Active+Service')
            ->assertOk()
            ->assertSee('Active Service Token')
            ->assertDontSee('Expired Legacy Token');

        // Search by numeric ID
        $this->actingAs($this->admin)
            ->get('/sanctum-manager?search='.$activeNew->accessToken->getKey())
            ->assertOk()
            ->assertSee('Active Service Token')
            ->assertDontSee('Expired Legacy Token');

        // Status filter: active
        $this->actingAs($this->admin)
            ->get('/sanctum-manager?status=active')
            ->assertOk()
            ->assertSee('Active Service Token')
            ->assertDontSee('Expired Legacy Token')
            ->assertDontSee('Revoked Compromised Token');

        // Status filter: grace
        $this->actingAs($this->admin)
            ->get('/sanctum-manager?status=grace')
            ->assertOk()
            ->assertSee('Grace Period Token')
            ->assertDontSee('Active Service Token')
            ->assertDontSee('Expired Legacy Token');

        // Status filter: expired
        $this->actingAs($this->admin)
            ->get('/sanctum-manager?status=expired')
            ->assertOk()
            ->assertSee('Expired Legacy Token')
            ->assertDontSee('Active Service Token');

        // Status filter: revoked
        $this->actingAs($this->admin)
            ->get('/sanctum-manager?status=revoked')
            ->assertOk()
            ->assertSee('Revoked Compromised Token')
            ->assertDontSee('Active Service Token');
    }

    public function test_issue_creates_token_with_all_options_and_flashes_plain_text_token(): void
    {
        /** @var ScopeRegistryContract $registry */
        $registry = app(ScopeRegistryContract::class);
        $registry->define('orders:read', 'Read orders', 'orders');
        $registry->define('users:read', 'Read users', 'users');
        $registry->define('reports:export', 'Export reports', 'reports');
        $registry->defineGroup('readonly', ['orders:read', 'users:read'], 'Read-only preset');

        $targetUser = $this->createUser('api-user@example.com', 'API User');

        $response = $this->actingAs($this->admin)->post('/sanctum-manager/tokens', [
            'user' => (string) $targetUser->getKey(),
            'name' => 'CI Pipeline Token',
            'scopes' => ['@readonly'],
            'custom_scopes' => 'reports:export, billing:view',
            'expires_in_days' => 14,
            'allowed_ips' => "10.0.0.1, 192.168.1.0/24\n203.0.113.5",
            'allowed_origins' => "https://app.example.com\nhttps://admin.example.com",
            'max_uses' => 250,
            'token_prefix' => 'sm_live_',
            'rate_limit_per_minute' => 120,
        ]);

        $response->assertRedirect(route('sanctum-manager.dashboard.index'))
            ->assertSessionHas('sanctum_manager_status')
            ->assertSessionHas('sanctum_manager_plain_token');

        /** @var array{name: string, token: string, action: string, grace_expires_at: ?string} $flashed */
        $flashed = session('sanctum_manager_plain_token');
        $this->assertSame('CI Pipeline Token', $flashed['name']);
        $this->assertSame('issued', $flashed['action']);
        $this->assertNull($flashed['grace_expires_at']);
        $this->assertStringContainsString('|sm_live_', $flashed['token']);

        /** @var PersonalAccessToken|null $token */
        $token = PersonalAccessToken::query()->where('name', 'CI Pipeline Token')->first();
        $this->assertNotNull($token);
        $this->assertSame($targetUser->getKey(), $token->tokenable_id);
        $this->assertEqualsCanonicalizing(
            ['orders:read', 'users:read', 'reports:export', 'billing:view'],
            $token->getEffectiveScopes(),
        );
        $this->assertSame(['10.0.0.1', '192.168.1.0/24', '203.0.113.5'], $token->getAllowedIps());
        $this->assertSame(['https://app.example.com', 'https://admin.example.com'], $token->getAllowedOrigins());
        $this->assertSame(250, $token->getMaxUses());
        $this->assertNotNull($token->expires_at);
        $this->assertSame(120, $token->getMetadata()['rate_limit_per_minute'] ?? null);

        // Following the redirect renders the one-time plain-text token banner
        $this->actingAs($this->admin)
            ->get(route('sanctum-manager.dashboard.index'))
            ->assertOk()
            ->assertSee($flashed['token']);
    }

    public function test_rotate_creates_replacement_token_and_puts_old_token_in_grace_period(): void
    {
        /** @var TokenManagerContract $manager */
        $manager = app(TokenManagerContract::class);
        $issued = $manager->issue($this->admin, 'Original API Key', ['orders:read']);

        /** @var PersonalAccessToken $oldToken */
        $oldToken = $issued->accessToken;

        $response = $this->actingAs($this->admin)->post("/sanctum-manager/tokens/{$oldToken->getKey()}/rotate", [
            'grace_period_minutes' => 60,
            'name' => 'Rotated API Key v2',
        ]);

        $response->assertRedirect(route('sanctum-manager.dashboard.index'))
            ->assertSessionHas('sanctum_manager_status', 'Token rotated successfully.')
            ->assertSessionHas('sanctum_manager_plain_token');

        /** @var array{name: string, token: string, action: string, grace_expires_at: ?string} $flashed */
        $flashed = session('sanctum_manager_plain_token');
        $this->assertSame('Rotated API Key v2', $flashed['name']);
        $this->assertSame('rotated', $flashed['action']);
        $this->assertNotNull($flashed['grace_expires_at']);
        $this->assertNotEmpty($flashed['token']);

        $oldToken->refresh();
        $this->assertTrue($oldToken->isInGracePeriod());

        $newToken = PersonalAccessToken::query()->where('name', 'Rotated API Key v2')->first();
        $this->assertNotNull($newToken);
    }

    public function test_revoke_single_token_with_reason(): void
    {
        config()->set('sanctum-manager.security.keep_revoked_tokens', true);

        /** @var TokenManagerContract $manager */
        $manager = app(TokenManagerContract::class);
        $issued = $manager->issue($this->admin, 'Temporary Debug Token', ['*']);

        /** @var PersonalAccessToken $token */
        $token = $issued->accessToken;

        $this->actingAs($this->admin)
            ->delete("/sanctum-manager/tokens/{$token->getKey()}", [
                'reason' => 'No longer needed',
            ])
            ->assertRedirect(route('sanctum-manager.dashboard.index'))
            ->assertSessionHas('sanctum_manager_status');

        $token->refresh();
        $this->assertTrue($token->isRevoked());
        $this->assertSame('No longer needed', $token->getRevocationReason());
    }

    public function test_revoke_user_tokens_revokes_all_active_tokens_by_email_and_id(): void
    {
        /** @var TokenManagerContract $manager */
        $manager = app(TokenManagerContract::class);

        $alice = $this->createUser('alice@example.com', 'Alice');
        $bob = $this->createUser('bob@example.com', 'Bob');

        $manager->issue($alice, 'Alice Token 1', ['*']);
        $manager->issue($alice, 'Alice Token 2', ['*']);
        $manager->issue($bob, 'Bob Token 1', ['*']);

        // Revoke Alice's tokens by email
        $this->actingAs($this->admin)
            ->post('/sanctum-manager/tokens/revoke-user', [
                'user' => 'alice@example.com',
                'reason' => 'Account offboarding',
            ])
            ->assertRedirect(route('sanctum-manager.dashboard.index'))
            ->assertSessionHas('sanctum_manager_status', 'Revoked 2 active token(s).');

        $this->assertSame(0, PersonalAccessToken::query()->where('tokenable_id', $alice->getKey())->active()->count());
        $this->assertSame(1, PersonalAccessToken::query()->where('tokenable_id', $bob->getKey())->active()->count());

        // Revoke Bob's tokens by numeric ID
        $this->actingAs($this->admin)
            ->post('/sanctum-manager/tokens/revoke-user', [
                'user' => (string) $bob->getKey(),
            ])
            ->assertRedirect(route('sanctum-manager.dashboard.index'))
            ->assertSessionHas('sanctum_manager_status', 'Revoked 1 active token(s).');

        $this->assertSame(0, PersonalAccessToken::query()->where('tokenable_id', $bob->getKey())->active()->count());
    }

    public function test_prune_removes_expired_tokens_and_stale_audit_logs_in_extended_mode(): void
    {
        $this->enableExtendedMigrations();

        /** @var TokenManagerContract $manager */
        $manager = app(TokenManagerContract::class);

        $stale = $manager->issue(
            $this->admin,
            'Old Expired Token',
            ['*'],
            TokenOptions::make()->expiresAt(now()->subDays(45)),
        );
        $fresh = $manager->issue($this->admin, 'Fresh Active Token', ['*']);

        PersonalAccessTokenLog::query()->create([
            'personal_access_token_id' => $fresh->accessToken->getKey(),
            'ip_address' => '127.0.0.1',
            'method' => 'GET',
            'path' => '/api/orders',
            'status_code' => 200,
            'user_agent' => 'PHPUnit',
            'created_at' => now()->subDays(120),
        ]);

        PersonalAccessTokenLog::query()->create([
            'personal_access_token_id' => $fresh->accessToken->getKey(),
            'ip_address' => '127.0.0.1',
            'method' => 'POST',
            'path' => '/api/orders',
            'status_code' => 201,
            'user_agent' => 'PHPUnit',
            'created_at' => now()->subDays(2),
        ]);

        $this->actingAs($this->admin)
            ->post('/sanctum-manager/tokens/prune', [
                'days' => 30,
                'log_days' => 90,
            ])
            ->assertRedirect(route('sanctum-manager.dashboard.index'))
            ->assertSessionHas('sanctum_manager_status', 'Pruned 1 token(s) and 1 audit log(s).');

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $stale->accessToken->getKey()]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $fresh->accessToken->getKey()]);
        $this->assertSame(1, PersonalAccessTokenLog::query()->count());
    }

    public function test_scopes_page_renders_registered_scopes_and_groups(): void
    {
        /** @var ScopeRegistryContract $registry */
        $registry = app(ScopeRegistryContract::class);
        $registry->define('invoices:read', 'Read customer invoices', 'billing', [], true, false);
        $registry->define('invoices:write', 'Create and update invoices', 'billing', ['invoices:read']);
        $registry->defineGroup('billing-admin', ['invoices:write'], 'Full billing access');

        $this->actingAs($this->admin)
            ->get('/sanctum-manager/scopes')
            ->assertOk()
            ->assertSee('invoices:read')
            ->assertSee('Read customer invoices')
            ->assertSee('invoices:write')
            ->assertSee('billing-admin')
            ->assertSee('Full billing access');
    }

    public function test_logs_page_shows_migration_notice_in_zero_migration_mode_and_entries_in_extended_mode(): void
    {
        // Zero-migration mode: table does not exist
        $this->actingAs($this->admin)
            ->get('/sanctum-manager/logs')
            ->assertOk()
            ->assertSee('sanctum_token_logs');

        // Extended mode: table exists and renders log entries
        $this->enableExtendedMigrations();

        /** @var TokenManagerContract $manager */
        $manager = app(TokenManagerContract::class);
        $issued = $manager->issue($this->admin, 'Audited Token', ['*']);

        PersonalAccessTokenLog::query()->create([
            'personal_access_token_id' => $issued->accessToken->getKey(),
            'ip_address' => '203.0.113.42',
            'method' => 'PATCH',
            'path' => '/api/v1/projects/99',
            'status_code' => 200,
            'user_agent' => 'SanctumTestClient/1.0',
            'created_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get('/sanctum-manager/logs')
            ->assertOk()
            ->assertSee('Audited Token')
            ->assertSee('203.0.113.42')
            ->assertSee('/api/v1/projects/99')
            ->assertSee('PATCH');
    }

    public function test_mail_settings_page_and_send_test_mail_endpoint(): void
    {
        Mail::fake();

        config()->set('sanctum-manager.mail.enabled', true);

        $this->actingAs($this->admin)
            ->get('/sanctum-manager/mail')
            ->assertOk()
            ->assertSee('viewSanctumManager')
            ->assertSee('admin@example.com');

        $this->actingAs($this->admin)
            ->post('/sanctum-manager/mail/test', [
                'email' => 'security@example.com',
            ])
            ->assertRedirect(route('sanctum-manager.dashboard.mail'))
            ->assertSessionHas('sanctum_manager_status');

        Mail::assertSent(TestNotificationMail::class, static function (TestNotificationMail $mail): bool {
            return $mail->hasTo('security@example.com')
                && $mail->recipientEmail === 'security@example.com';
        });
    }
}
