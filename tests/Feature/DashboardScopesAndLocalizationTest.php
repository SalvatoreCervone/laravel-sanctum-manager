<?php

declare(strict_types=1);

namespace SanctumManager\Tests\Feature;

use SanctumManager\Contracts\ScopeRegistryContract;
use SanctumManager\Services\ScopeStore;
use SanctumManager\Tests\Fixtures\User;
use SanctumManager\Tests\TestCase;

class DashboardScopesAndLocalizationTest extends TestCase
{
    protected User $admin;

    protected string $tempScopesFile;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        config()->set('sanctum-manager.dashboard.allowed_emails', ['admin@example.com']);

        $this->tempScopesFile = sys_get_temp_dir().'/sanctum-manager-scopes-'.uniqid('', true).'.json';
        config()->set('sanctum-manager.scopes.storage_path', $this->tempScopesFile);

        $this->admin = $this->createUser('admin@example.com', 'Dashboard Admin');
    }

    protected function tearDown(): void
    {
        if (is_file($this->tempScopesFile)) {
            @unlink($this->tempScopesFile);
        }

        parent::tearDown();
    }

    public function test_scope_crud_in_file_fallback_mode(): void
    {
        /** @var ScopeStore $store */
        $store = app(ScopeStore::class);
        $this->assertSame('file', $store->activeDriver());

        // Create scope (also verifies self-implication is stripped)
        $this->actingAs($this->admin)
            ->post('/sanctum-manager/scopes', [
                'name' => 'orders:write',
                'description' => 'Create and update orders',
                'group' => 'orders',
                'implies' => ['orders:read'],
                'custom_implies' => 'orders:write, billing:read',
                'is_default' => '1',
                'is_deprecated' => '0',
            ])
            ->assertRedirect(route('sanctum-manager.dashboard.scopes'))
            ->assertSessionHas('sanctum_manager_status', 'Scope "orders:write" saved successfully.');

        $this->assertFileExists($this->tempScopesFile);

        /** @var ScopeRegistryContract $registry */
        $registry = app(ScopeRegistryContract::class);
        $created = $registry->get('orders:write');
        $this->assertNotNull($created);
        $this->assertSame('Create and update orders', $created->description);
        $this->assertSame('orders', $created->group);
        $this->assertSame(['orders:read', 'billing:read'], $created->implies);
        $this->assertTrue($created->isDefault);
        $this->assertFalse($created->isDeprecated);

        // Page renders saved scope
        $this->actingAs($this->admin)
            ->get('/sanctum-manager/scopes')
            ->assertOk()
            ->assertSee('orders:write')
            ->assertSee('Create and update orders');

        // Edit existing scope
        $this->actingAs($this->admin)
            ->post('/sanctum-manager/scopes', [
                'name' => 'orders:write',
                'description' => 'Updated orders write scope',
                'group' => 'commerce',
                'implies' => ['orders:read'],
                'is_default' => '0',
                'is_deprecated' => '1',
            ])
            ->assertRedirect(route('sanctum-manager.dashboard.scopes'))
            ->assertSessionHas('sanctum_manager_status', 'Scope "orders:write" saved successfully.');

        $updated = $registry->get('orders:write');
        $this->assertNotNull($updated);
        $this->assertSame('Updated orders write scope', $updated->description);
        $this->assertSame('commerce', $updated->group);
        $this->assertFalse($updated->isDefault);
        $this->assertTrue($updated->isDeprecated);

        // Delete scope
        $this->actingAs($this->admin)
            ->delete('/sanctum-manager/scopes/orders:write')
            ->assertRedirect(route('sanctum-manager.dashboard.scopes'))
            ->assertSessionHas('sanctum_manager_status', 'Scope "orders:write" deleted.');

        $this->assertNotContains('orders:write', $store->persistedScopeNames());
    }

    public function test_scope_crud_in_database_mode(): void
    {
        $this->enableScopesMigrations();

        /** @var ScopeStore $store */
        $store = app(ScopeStore::class);
        $this->assertSame('database', $store->activeDriver());

        // Create scope in database
        $this->actingAs($this->admin)
            ->post('/sanctum-manager/scopes', [
                'name' => 'projects.admin',
                'description' => 'Manage projects',
                'group' => 'projects',
                'custom_implies' => 'projects.read, projects.write',
                'is_default' => '0',
                'is_deprecated' => '0',
            ])
            ->assertRedirect(route('sanctum-manager.dashboard.scopes'))
            ->assertSessionHas('sanctum_manager_status', 'Scope "projects.admin" saved successfully.');

        $this->assertDatabaseHas('sanctum_scopes', [
            'name' => 'projects.admin',
            'description' => 'Manage projects',
            'group' => 'projects',
        ]);

        // Edit scope in database
        $this->actingAs($this->admin)
            ->post('/sanctum-manager/scopes', [
                'name' => 'projects.admin',
                'description' => 'Full project administration',
                'group' => 'projects',
                'is_deprecated' => '1',
            ])
            ->assertRedirect(route('sanctum-manager.dashboard.scopes'));

        $this->assertDatabaseHas('sanctum_scopes', [
            'name' => 'projects.admin',
            'description' => 'Full project administration',
            'is_deprecated' => true,
        ]);

        // Delete scope from database
        $this->actingAs($this->admin)
            ->delete('/sanctum-manager/scopes/projects.admin')
            ->assertRedirect(route('sanctum-manager.dashboard.scopes'))
            ->assertSessionHas('sanctum_manager_status', 'Scope "projects.admin" deleted.');

        $this->assertDatabaseMissing('sanctum_scopes', [
            'name' => 'projects.admin',
        ]);
    }

    public function test_group_crud_and_validation_in_file_fallback_mode(): void
    {
        /** @var ScopeStore $store */
        $store = app(ScopeStore::class);
        $this->assertSame('file', $store->activeDriver());

        // Validation error when no scopes are provided
        $this->actingAs($this->admin)
            ->post('/sanctum-manager/scopes/groups', [
                'name' => 'empty-group',
                'description' => 'Should fail',
            ])
            ->assertRedirect()
            ->assertSessionHas('sanctum_manager_error', 'Please select or enter at least one scope for the group.');

        // Create group
        $this->actingAs($this->admin)
            ->post('/sanctum-manager/scopes/groups', [
                'name' => 'support-team',
                'description' => 'Support staff scopes',
                'scopes' => ['tickets:read'],
                'custom_scopes' => 'users:read, tickets:reply',
            ])
            ->assertRedirect(route('sanctum-manager.dashboard.scopes'))
            ->assertSessionHas('sanctum_manager_status', 'Scope group "@support-team" saved successfully.');

        /** @var ScopeRegistryContract $registry */
        $registry = app(ScopeRegistryContract::class);
        $groups = $registry->groups();
        $this->assertArrayHasKey('support-team', $groups);
        $this->assertSame(['tickets:read', 'users:read', 'tickets:reply'], $groups['support-team']->scopes);

        // Edit group
        $this->actingAs($this->admin)
            ->post('/sanctum-manager/scopes/groups', [
                'name' => 'support-team',
                'description' => 'Updated support staff scopes',
                'custom_scopes' => 'tickets:read, tickets:close',
            ])
            ->assertRedirect(route('sanctum-manager.dashboard.scopes'));

        $updatedGroups = $registry->groups();
        $this->assertSame('Updated support staff scopes', $updatedGroups['support-team']->description);
        $this->assertSame(['tickets:read', 'tickets:close'], $updatedGroups['support-team']->scopes);

        // Delete group
        $this->actingAs($this->admin)
            ->delete('/sanctum-manager/scopes/groups/support-team')
            ->assertRedirect(route('sanctum-manager.dashboard.scopes'))
            ->assertSessionHas('sanctum_manager_status', 'Scope group "@support-team" deleted.');

        $this->assertNotContains('support-team', $store->persistedGroupNames());
    }

    public function test_group_crud_in_database_mode(): void
    {
        $this->enableScopesMigrations();

        /** @var ScopeStore $store */
        $store = app(ScopeStore::class);
        $this->assertSame('database', $store->activeDriver());

        // Create group
        $this->actingAs($this->admin)
            ->post('/sanctum-manager/scopes/groups', [
                'name' => 'billing-ops',
                'description' => 'Billing operators',
                'custom_scopes' => 'invoices:read, invoices:refund',
            ])
            ->assertRedirect(route('sanctum-manager.dashboard.scopes'))
            ->assertSessionHas('sanctum_manager_status', 'Scope group "@billing-ops" saved successfully.');

        $this->assertDatabaseHas('sanctum_scope_groups', [
            'name' => 'billing-ops',
            'description' => 'Billing operators',
        ]);

        // Edit group
        $this->actingAs($this->admin)
            ->post('/sanctum-manager/scopes/groups', [
                'name' => 'billing-ops',
                'description' => 'Senior billing operators',
                'custom_scopes' => 'invoices:read',
            ])
            ->assertRedirect(route('sanctum-manager.dashboard.scopes'));

        $this->assertDatabaseHas('sanctum_scope_groups', [
            'name' => 'billing-ops',
            'description' => 'Senior billing operators',
        ]);

        // Delete group
        $this->actingAs($this->admin)
            ->delete('/sanctum-manager/scopes/groups/billing-ops')
            ->assertRedirect(route('sanctum-manager.dashboard.scopes'))
            ->assertSessionHas('sanctum_manager_status', 'Scope group "@billing-ops" deleted.');

        $this->assertDatabaseMissing('sanctum_scope_groups', [
            'name' => 'billing-ops',
        ]);
    }

    public function test_deleting_code_defined_scope_or_group_flashes_error(): void
    {
        /** @var ScopeRegistryContract $registry */
        $registry = app(ScopeRegistryContract::class);
        $registry->define('system:health', 'Health check scope');
        $registry->defineGroup('readonly-code', ['system:health'], 'Defined in code');

        $this->actingAs($this->admin)
            ->delete('/sanctum-manager/scopes/system:health')
            ->assertRedirect(route('sanctum-manager.dashboard.scopes'))
            ->assertSessionHas(
                'sanctum_manager_error',
                'Scope "system:health" is defined in code/config and cannot be deleted from the dashboard.',
            );

        $this->actingAs($this->admin)
            ->delete('/sanctum-manager/scopes/groups/readonly-code')
            ->assertRedirect(route('sanctum-manager.dashboard.scopes'))
            ->assertSessionHas(
                'sanctum_manager_error',
                'Scope group "@readonly-code" is defined in code/config and cannot be deleted from the dashboard.',
            );
    }

    public function test_italian_locale_renders_italian_dashboard_pages_and_flash_messages(): void
    {
        app()->setLocale('it');

        $this->actingAs($this->admin)
            ->get('/sanctum-manager')
            ->assertOk()
            ->assertSee('Nuovo Token')
            ->assertSee('Scope e Gruppi');

        $this->actingAs($this->admin)
            ->get('/sanctum-manager/scopes')
            ->assertOk()
            ->assertSee('Scope e Gruppi')
            ->assertSee('Nuovo Scope')
            ->assertSee('Nuovo Gruppo');

        $this->actingAs($this->admin)
            ->get('/sanctum-manager/logs')
            ->assertOk()
            ->assertSee('Audit Log')
            ->assertSee('Tabella Audit Log Non Migrata');

        $this->actingAs($this->admin)
            ->get('/sanctum-manager/mail')
            ->assertOk()
            ->assertSee('Notifiche Email e Gate di Accesso')
            ->assertSee('Invia Email di Test');

        // Italian flash message on scope save
        $this->actingAs($this->admin)
            ->post('/sanctum-manager/scopes', [
                'name' => 'ordini:leggi',
                'description' => 'Lettura ordini',
            ])
            ->assertRedirect(route('sanctum-manager.dashboard.scopes'))
            ->assertSessionHas('sanctum_manager_status', 'Scope "ordini:leggi" salvato con successo.');

        // Italian flash error on empty group
        $this->actingAs($this->admin)
            ->post('/sanctum-manager/scopes/groups', [
                'name' => 'vuoto',
            ])
            ->assertRedirect()
            ->assertSessionHas('sanctum_manager_error', 'Seleziona o inserisci almeno uno scope per il gruppo.');
    }
}
