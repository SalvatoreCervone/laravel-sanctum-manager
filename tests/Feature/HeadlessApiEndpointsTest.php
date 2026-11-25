<?php

declare(strict_types=1);

namespace SanctumManager\Tests\Feature;

use Laravel\Sanctum\Sanctum;
use SanctumManager\Facades\SanctumManager;
use SanctumManager\Models\PersonalAccessToken;
use SanctumManager\Tests\TestCase;

class HeadlessApiEndpointsTest extends TestCase
{
    public function test_get_scopes_endpoint_returns_registered_scopes_and_groups(): void
    {
        SanctumManager::defineScope('orders:read', 'Read orders', group: 'orders', isDefault: true);
        SanctumManager::defineScope('orders:write', 'Write orders', group: 'orders', implies: ['orders:read']);
        SanctumManager::defineGroup('readonly', ['orders:read'], 'Read-only access');

        $user = $this->createUser();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/sanctum-manager/scopes');

        $response->assertOk()
            ->assertJsonCount(2, 'scopes')
            ->assertJsonCount(1, 'groups')
            ->assertJsonFragment([
                'name' => 'orders:read',
                'description' => 'Read orders',
                'group' => 'orders',
                'is_default' => true,
            ])
            ->assertJsonFragment([
                'name' => 'readonly',
                'description' => 'Read-only access',
                'scopes' => ['orders:read'],
            ]);
    }

    public function test_post_tokens_endpoint_creates_token_and_returns_plain_text_and_metadata(): void
    {
        $user = $this->createUser();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/sanctum-manager/tokens', [
            'name' => 'CI Pipeline Token',
            'scopes' => ['orders:read', 'orders:write'],
            'expires_in_minutes' => 120,
            'allowed_ips' => ['10.0.0.0/24'],
            'allowed_origins' => ['https://ci.example.com'],
            'max_uses' => 50,
            'metadata' => ['env' => 'staging'],
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'plain_text_token',
                'token' => [
                    'id',
                    'name',
                    'scopes',
                    'last_used_at',
                    'expires_at',
                    'revoked_at',
                    'created_at',
                    'allowed_ips',
                    'allowed_origins',
                    'max_uses',
                    'uses_count',
                    'metadata',
                ],
            ])
            ->assertJsonPath('token.name', 'CI Pipeline Token')
            ->assertJsonPath('token.scopes', ['orders:read', 'orders:write'])
            ->assertJsonPath('token.allowed_ips', ['10.0.0.0/24'])
            ->assertJsonPath('token.allowed_origins', ['https://ci.example.com'])
            ->assertJsonPath('token.max_uses', 50)
            ->assertJsonPath('token.metadata', ['env' => 'staging']);

        $plainText = $response->json('plain_text_token');
        $this->assertIsString($plainText);
        $this->assertStringContainsString('|', $plainText);
    }

    public function test_get_tokens_endpoint_lists_only_authenticated_users_tokens_without_hashed_secret(): void
    {
        $owner = $this->createUser('owner@example.com', 'Owner');
        $otherUser = $this->createUser('other@example.com', 'Other');

        SanctumManager::issue($owner, 'Owner Token 1', ['orders:read']);
        SanctumManager::issue($owner, 'Owner Token 2', ['orders:write']);
        SanctumManager::issue($otherUser, 'Other User Token', ['orders:read']);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/sanctum-manager/tokens');

        $response->assertOk()
            ->assertJsonCount(2, 'tokens');

        /** @var array<int, array<string, mixed>> $tokens */
        $tokens = $response->json('tokens');
        $names = array_column($tokens, 'name');

        $this->assertContains('Owner Token 1', $names);
        $this->assertContains('Owner Token 2', $names);
        $this->assertNotContains('Other User Token', $names);

        foreach ($tokens as $serialized) {
            $this->assertArrayNotHasKey('token', $serialized);
        }
    }

    public function test_rotate_endpoint_rotates_own_token_and_returns_404_for_another_users_token(): void
    {
        $owner = $this->createUser('owner@example.com', 'Owner');
        $otherUser = $this->createUser('other@example.com', 'Other');

        $ownToken = SanctumManager::issue($owner, 'Primary Key', ['orders:read']);
        $otherToken = SanctumManager::issue($otherUser, 'Foreign Key', ['orders:read']);

        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/sanctum-manager/tokens/{$ownToken->accessToken->getKey()}/rotate", [
            'grace_period_minutes' => 45,
            'name' => 'Primary Key v2',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'plain_text_token',
                'grace_expires_at',
                'token' => ['id', 'name', 'scopes'],
            ])
            ->assertJsonPath('token.name', 'Primary Key v2');

        $this->assertNotSame($ownToken->plainTextToken, $response->json('plain_text_token'));

        // Attempting to rotate another user's token returns 404
        $this->postJson("/api/sanctum-manager/tokens/{$otherToken->accessToken->getKey()}/rotate")
            ->assertNotFound();
    }

    public function test_destroy_endpoint_revokes_own_token_and_returns_404_for_another_users_token(): void
    {
        $owner = $this->createUser('owner@example.com', 'Owner');
        $otherUser = $this->createUser('other@example.com', 'Other');

        $ownToken = SanctumManager::issue($owner, 'Disposable Key', ['orders:read']);
        $otherToken = SanctumManager::issue($otherUser, 'Foreign Key', ['orders:read']);

        Sanctum::actingAs($owner);

        $this->deleteJson("/api/sanctum-manager/tokens/{$ownToken->accessToken->getKey()}")
            ->assertOk()
            ->assertJson(['message' => 'Token revoked successfully.']);

        $this->assertNull(PersonalAccessToken::query()->find($ownToken->accessToken->getKey()));

        // Attempting to revoke another user's token returns 404 and leaves it intact
        $this->deleteJson("/api/sanctum-manager/tokens/{$otherToken->accessToken->getKey()}")
            ->assertNotFound();

        $this->assertNotNull(PersonalAccessToken::query()->find($otherToken->accessToken->getKey()));
    }

    public function test_headless_endpoints_require_authentication(): void
    {
        $this->getJson('/api/sanctum-manager/scopes')->assertUnauthorized();
        $this->getJson('/api/sanctum-manager/tokens')->assertUnauthorized();
        $this->postJson('/api/sanctum-manager/tokens', ['name' => 'Anon'])->assertUnauthorized();
    }
}
