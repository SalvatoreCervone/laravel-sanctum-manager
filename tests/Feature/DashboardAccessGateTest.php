<?php

declare(strict_types=1);

namespace SanctumManager\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use SanctumManager\Tests\TestCase;

class DashboardAccessGateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        config()->set('sanctum-manager.dashboard.allowed_emails', []);
        config()->set('sanctum-manager.dashboard.allow_in_local', true);
    }

    public function test_guest_is_denied_in_non_local_environment(): void
    {
        $this->get('/sanctum-manager')
            ->assertForbidden();
    }

    public function test_authenticated_user_not_in_allowed_emails_is_denied(): void
    {
        config()->set('sanctum-manager.dashboard.allowed_emails', ['admin@example.com']);

        $user = $this->createUser('outsider@example.com', 'Outsider');

        $this->actingAs($user)
            ->get('/sanctum-manager')
            ->assertForbidden();
    }

    public function test_authenticated_user_in_allowed_emails_array_is_granted_case_insensitively(): void
    {
        config()->set('sanctum-manager.dashboard.allowed_emails', ['  Admin@Example.COM  ', 'ops@example.com']);

        $admin = $this->createUser('admin@example.com', 'Admin');

        $this->actingAs($admin)
            ->get('/sanctum-manager')
            ->assertOk()
            ->assertSee('Sanctum Manager');
    }

    public function test_authenticated_user_in_comma_separated_allowed_emails_string_is_granted(): void
    {
        config()->set('sanctum-manager.dashboard.allowed_emails', 'first@example.com,  SECOND@Example.com , third@example.com');

        $user = $this->createUser('second@example.com', 'Second Admin');

        $this->actingAs($user)
            ->get('/sanctum-manager')
            ->assertOk();
    }

    public function test_custom_gate_definition_overrides_default_behavior(): void
    {
        config()->set('sanctum-manager.dashboard.allowed_emails', ['listed@example.com']);

        $listed = $this->createUser('listed@example.com', 'Listed');
        $vip = $this->createUser('vip@example.com', 'VIP');

        Gate::define('viewSanctumManager', static fn (mixed $user = null): bool => is_object($user) && ($user->email ?? null) === 'vip@example.com');

        $this->actingAs($vip)
            ->get('/sanctum-manager')
            ->assertOk();

        $this->actingAs($listed)
            ->get('/sanctum-manager')
            ->assertForbidden();
    }

    public function test_local_environment_grants_access_when_allow_in_local_is_true(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'local');
        config()->set('sanctum-manager.dashboard.allow_in_local', true);
        config()->set('sanctum-manager.dashboard.allowed_emails', []);

        $this->get('/sanctum-manager')
            ->assertOk();
    }

    public function test_local_environment_denies_access_when_allow_in_local_is_false_and_email_not_allowed(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'local');
        config()->set('sanctum-manager.dashboard.allow_in_local', false);
        config()->set('sanctum-manager.dashboard.allowed_emails', []);

        $user = $this->createUser('dev@example.com', 'Developer');

        $this->actingAs($user)
            ->get('/sanctum-manager')
            ->assertForbidden();
    }
}
