<?php

declare(strict_types=1);

namespace SanctumManager\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use SanctumManager\DataTransferObjects\TokenOptions;
use SanctumManager\Facades\SanctumManager;
use SanctumManager\Models\PersonalAccessToken;
use SanctumManager\Models\PersonalAccessTokenLog;
use SanctumManager\Tests\TestCase;

class ArtisanCommandsTest extends TestCase
{
    public function test_scopes_command_lists_registered_scopes_and_groups(): void
    {
        SanctumManager::defineScope('orders:read', 'Read orders', group: 'orders', isDefault: true);
        SanctumManager::defineScope('orders:write', 'Write orders', group: 'orders', implies: ['orders:read']);
        SanctumManager::defineGroup('readonly', ['orders:read'], 'Read-only access');

        $this->artisan('sanctum-manager:scopes')
            ->expectsTable(
                ['Name', 'Group', 'Description', 'Implies', 'Default', 'Deprecated'],
                [
                    ['orders:read', 'orders', 'Read orders', '-', 'Yes', 'No'],
                    ['orders:write', 'orders', 'Write orders', 'orders:read', 'No', 'No'],
                ],
            )
            ->expectsTable(
                ['Group', 'Description', 'Scopes'],
                [
                    ['@readonly', 'Read-only access', 'orders:read'],
                ],
            )
            ->assertExitCode(0);
    }

    public function test_issue_command_creates_token_by_email_and_by_id_with_options(): void
    {
        $user = $this->createUser('cli@example.com', 'CLI User');

        $this->artisan('sanctum-manager:issue', [
            'user' => 'cli@example.com',
            'name' => 'CLI Email Token',
            '--scopes' => ['orders:read', 'orders:write'],
            '--expires' => '60',
            '--ips' => ['10.0.0.1', '10.0.0.0/24'],
            '--max-uses' => '25',
        ])
            ->expectsOutputToContain('Personal access token issued successfully.')
            ->expectsOutputToContain('Plain-Text Token')
            ->assertExitCode(0);

        /** @var PersonalAccessToken|null $emailToken */
        $emailToken = PersonalAccessToken::query()->where('name', 'CLI Email Token')->first();
        $this->assertNotNull($emailToken);
        $this->assertSame(['orders:read', 'orders:write'], $emailToken->getEffectiveScopes());
        $this->assertNotNull($emailToken->expires_at);
        $this->assertSame(['10.0.0.1', '10.0.0.0/24'], $emailToken->getAllowedIps());
        $this->assertSame(25, $emailToken->getMaxUses());

        // Issue by numeric user ID
        $this->artisan('sanctum-manager:issue', [
            'user' => (string) $user->getKey(),
            'name' => 'CLI ID Token',
            '--scopes' => ['orders:read'],
        ])
            ->expectsOutputToContain('Personal access token issued successfully.')
            ->expectsOutputToContain('Plain-Text Token')
            ->assertExitCode(0);

        /** @var PersonalAccessToken|null $idToken */
        $idToken = PersonalAccessToken::query()->where('name', 'CLI ID Token')->first();
        $this->assertNotNull($idToken);
        $this->assertSame($user->getKey(), $idToken->tokenable_id);
    }

    public function test_revoke_command_revokes_single_token_and_all_user_tokens(): void
    {
        $user = $this->createUser('revoke@example.com', 'Revoke User');

        $tokenA = SanctumManager::issue($user, 'Token A', ['orders:read']);
        $tokenB = SanctumManager::issue($user, 'Token B', ['orders:read']);
        $tokenC = SanctumManager::issue($user, 'Token C', ['orders:write']);

        // Revoke single token by ID
        $this->artisan('sanctum-manager:revoke', [
            'token' => (string) $tokenA->accessToken->getKey(),
        ])
            ->expectsOutputToContain("Token [{$tokenA->accessToken->getKey()}] has been revoked.")
            ->assertExitCode(0);

        $this->assertNull(PersonalAccessToken::query()->find($tokenA->accessToken->getKey()));
        $this->assertNotNull(PersonalAccessToken::query()->find($tokenB->accessToken->getKey()));

        // Revoke all remaining tokens for user by email
        $this->artisan('sanctum-manager:revoke', [
            '--user' => 'revoke@example.com',
        ])
            ->expectsOutputToContain('Revoked 2 token(s) for user [revoke@example.com].')
            ->assertExitCode(0);

        $this->assertSame(0, PersonalAccessToken::query()->count());
        $this->assertNull(PersonalAccessToken::query()->find($tokenC->accessToken->getKey()));
    }

    public function test_rotate_command_rotates_token_and_outputs_new_plain_text_token(): void
    {
        $user = $this->createUser();
        $issued = SanctumManager::issue($user, 'Rotate Me', ['orders:read']);

        $this->artisan('sanctum-manager:rotate', [
            'token' => (string) $issued->accessToken->getKey(),
            '--grace' => '15',
            '--name' => 'Rotated CLI Token',
        ])
            ->expectsOutputToContain('Personal access token rotated successfully.')
            ->expectsOutputToContain('New Plain-Text Token')
            ->expectsOutputToContain('Rotated CLI Token')
            ->assertExitCode(0);

        $this->assertSame(2, PersonalAccessToken::query()->count());

        /** @var PersonalAccessToken|null $newToken */
        $newToken = PersonalAccessToken::query()->where('name', 'Rotated CLI Token')->first();
        $this->assertNotNull($newToken);
        $this->assertSame(['orders:read'], $newToken->getEffectiveScopes());
    }

    public function test_prune_command_prunes_expired_tokens_in_zero_migration_mode(): void
    {
        $user = $this->createUser();

        $oldExpired = SanctumManager::issue(
            $user,
            'Old Expired',
            ['orders:read'],
            TokenOptions::make()->expiresAt(Carbon::now()->subDays(45)),
        );

        $recentExpired = SanctumManager::issue(
            $user,
            'Recent Expired',
            ['orders:read'],
            TokenOptions::make()->expiresAt(Carbon::now()->subDays(5)),
        );

        $active = SanctumManager::issue(
            $user,
            'Active Token',
            ['orders:read'],
            TokenOptions::make()->expiresAt(Carbon::now()->addDays(10)),
        );

        $this->artisan('sanctum-manager:prune', ['--days' => '30'])
            ->expectsOutputToContain('Sanctum Manager pruning completed.')
            ->assertExitCode(0);

        $this->assertNull(PersonalAccessToken::query()->find($oldExpired->accessToken->getKey()));
        $this->assertNotNull(PersonalAccessToken::query()->find($recentExpired->accessToken->getKey()));
        $this->assertNotNull(PersonalAccessToken::query()->find($active->accessToken->getKey()));
    }

    public function test_prune_command_prunes_revoked_tokens_and_old_logs_in_extended_mode(): void
    {
        $this->enableExtendedMigrations();
        config()->set('sanctum-manager.security.keep_revoked_tokens', true);

        $user = $this->createUser();

        $oldExpired = SanctumManager::issue(
            $user,
            'Old Expired',
            ['orders:read'],
            TokenOptions::make()->expiresAt(Carbon::now()->subDays(40)),
        );

        $oldRevoked = SanctumManager::issue($user, 'Old Revoked', ['orders:read']);
        /** @var PersonalAccessToken $oldRevokedModel */
        $oldRevokedModel = $oldRevoked->accessToken;
        $oldRevokedModel->forceFill(['revoked_at' => Carbon::now()->subDays(40)])->save();

        $active = SanctumManager::issue($user, 'Still Active', ['orders:read']);

        $oldLog = PersonalAccessTokenLog::query()->create([
            'personal_access_token_id' => $active->accessToken->getKey(),
            'ip_address' => '127.0.0.1',
            'method' => 'GET',
            'path' => 'api/orders',
            'user_agent' => 'PHPUnit',
            'created_at' => Carbon::now()->subDays(120),
        ]);

        $recentLog = PersonalAccessTokenLog::query()->create([
            'personal_access_token_id' => $active->accessToken->getKey(),
            'ip_address' => '127.0.0.1',
            'method' => 'GET',
            'path' => 'api/orders',
            'user_agent' => 'PHPUnit',
            'created_at' => Carbon::now()->subDays(10),
        ]);

        $this->artisan('sanctum-manager:prune', [
            '--days' => '30',
            '--log-days' => '90',
        ])
            ->expectsOutputToContain('Sanctum Manager pruning completed.')
            ->assertExitCode(0);

        $this->assertNull(PersonalAccessToken::query()->find($oldExpired->accessToken->getKey()));
        $this->assertNull(PersonalAccessToken::query()->find($oldRevokedModel->getKey()));
        $this->assertNotNull(PersonalAccessToken::query()->find($active->accessToken->getKey()));

        $this->assertNull(PersonalAccessTokenLog::query()->find($oldLog->getKey()));
        $this->assertNotNull(PersonalAccessTokenLog::query()->find($recentLog->getKey()));
    }

    public function test_install_command_runs_cleanly_in_zero_migration_mode_with_no_migrate(): void
    {
        $publishedConfig = config_path('sanctum-manager.php');

        try {
            $this->artisan('sanctum-manager:install', [
                '--no-migrate' => true,
            ])
                ->expectsConfirmation(
                    'Would you like to publish the OPTIONAL extended columns & audit log migrations? (Select No for Zero-Migration mode)',
                    'no',
                )
                ->expectsOutputToContain('Zero-Migration mode active: no extra package migrations required.')
                ->expectsOutputToContain('Laravel Sanctum Manager installed successfully.')
                ->assertExitCode(0);
        } finally {
            if (File::exists($publishedConfig)) {
                File::delete($publishedConfig);
            }
        }
    }
}
