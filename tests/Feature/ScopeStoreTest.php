<?php

declare(strict_types=1);

namespace SanctumManager\Tests\Feature;

use Illuminate\Support\Facades\File;
use SanctumManager\Contracts\ScopeRegistryContract;
use SanctumManager\DataTransferObjects\ScopeDefinition;
use SanctumManager\DataTransferObjects\ScopeGroup;
use SanctumManager\Models\SanctumScope;
use SanctumManager\Models\SanctumScopeGroup;
use SanctumManager\Scopes\ScopeRegistry;
use SanctumManager\Services\ScopeStore;
use SanctumManager\Tests\TestCase;

class ScopeStoreTest extends TestCase
{
    protected string $tempStorageDir;

    protected string $tempStoragePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempStorageDir = sys_get_temp_dir().'/sanctum-scope-store-'.uniqid('', true);
        $this->tempStoragePath = $this->tempStorageDir.'/scopes.json';

        config()->set('sanctum-manager.scopes.storage_driver', 'auto');
        config()->set('sanctum-manager.scopes.storage_path', $this->tempStoragePath);
        config()->set('sanctum-manager.scopes.definitions', []);
        config()->set('sanctum-manager.scopes.groups', []);
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->tempStorageDir)) {
            File::deleteDirectory($this->tempStorageDir);
        }

        parent::tearDown();
    }

    public function test_auto_mode_falls_back_to_file_driver_when_tables_are_not_migrated(): void
    {
        $store = $this->app->make(ScopeStore::class);

        $this->assertSame('file', $store->activeDriver());
        $this->assertFalse($store->usesDatabase());
        $this->assertSame($this->tempStoragePath, $store->storagePath());
        $this->assertSame([], $store->allScopes());
        $this->assertSame([], $store->allGroups());
    }

    public function test_file_mode_saves_deletes_and_syncs_scopes(): void
    {
        $registry = $this->app->make(ScopeRegistryContract::class);
        $store = $this->app->make(ScopeStore::class);

        $scope = new ScopeDefinition(
            name: 'orders:write',
            description: 'Create and update orders',
            group: 'orders',
            implies: ['orders:read'],
            isDefault: true,
            isDeprecated: false,
        );

        $store->saveScope($scope);

        $this->assertFileExists($this->tempStoragePath);
        $this->assertSame(['orders:write'], $store->persistedScopeNames());
        $this->assertTrue($registry->has('orders:write'));
        $this->assertSame('Create and update orders', $registry->get('orders:write')?->description);
        $this->assertSame(['orders:read'], $registry->get('orders:write')?->implies);

        // Verify persistence survives syncToRegistry() on a fresh ScopeRegistry
        $freshRegistry = new ScopeRegistry;
        $store->syncToRegistry($freshRegistry);

        $synced = $freshRegistry->get('orders:write');
        $this->assertNotNull($synced);
        $this->assertSame('orders:write', $synced->name);
        $this->assertSame('Create and update orders', $synced->description);
        $this->assertSame('orders', $synced->group);
        $this->assertSame(['orders:read'], $synced->implies);
        $this->assertTrue($synced->isDefault);
        $this->assertFalse($synced->isDeprecated);

        // Upsert existing scope
        $store->saveScope(new ScopeDefinition(
            name: 'orders:write',
            description: 'Updated description',
            group: 'orders',
            implies: ['orders:read', 'orders:list'],
            isDefault: false,
            isDeprecated: true,
        ));

        $this->assertSame('Updated description', $registry->get('orders:write')?->description);
        $this->assertTrue($registry->get('orders:write')?->isDeprecated);

        // Delete persisted scope
        $this->assertTrue($store->deleteScope('orders:write'));
        $this->assertFalse($registry->has('orders:write'));
        $this->assertSame([], $store->persistedScopeNames());
        $this->assertFalse($store->deleteScope('orders:write'));
    }

    public function test_file_mode_saves_deletes_and_syncs_groups(): void
    {
        $registry = $this->app->make(ScopeRegistryContract::class);
        $store = $this->app->make(ScopeStore::class);

        $group = new ScopeGroup(
            name: 'editor',
            scopes: ['posts:read', 'posts:write'],
            description: 'Editorial access',
        );

        $store->saveGroup($group);

        $this->assertFileExists($this->tempStoragePath);
        $this->assertSame(['editor'], $store->persistedGroupNames());
        $this->assertArrayHasKey('editor', $registry->groups());
        $this->assertSame(['posts:read', 'posts:write'], $registry->groups()['editor']->scopes);

        // Verify persistence survives syncToRegistry() on a fresh ScopeRegistry
        $freshRegistry = new ScopeRegistry;
        $store->syncToRegistry($freshRegistry);

        $this->assertArrayHasKey('editor', $freshRegistry->groups());
        $this->assertSame('Editorial access', $freshRegistry->groups()['editor']->description);
        $this->assertSame(['posts:read', 'posts:write'], $freshRegistry->groups()['editor']->scopes);

        // Delete persisted group
        $this->assertTrue($store->deleteGroup('editor'));
        $this->assertArrayNotHasKey('editor', $registry->groups());
        $this->assertSame([], $store->persistedGroupNames());
        $this->assertFalse($store->deleteGroup('editor'));
    }

    public function test_file_mode_deleting_persisted_override_restores_config_definitions(): void
    {
        config()->set('sanctum-manager.scopes.definitions', [
            'orders:read' => [
                'description' => 'Config read orders',
                'group' => 'orders',
                'implies' => [],
                'is_default' => true,
            ],
        ]);
        config()->set('sanctum-manager.scopes.groups', [
            'readonly' => [
                'description' => 'Config read-only group',
                'scopes' => ['orders:read'],
            ],
        ]);

        /** @var ScopeRegistry $registry */
        $registry = $this->app->make(ScopeRegistryContract::class);
        $registry->loadFromConfig((array) config('sanctum-manager.scopes', []));

        $store = $this->app->make(ScopeStore::class);

        // Override both in file store
        $store->saveScope(new ScopeDefinition(
            name: 'orders:read',
            description: 'Overridden read orders',
            group: 'custom',
            isDefault: false,
        ));
        $store->saveGroup(new ScopeGroup(
            name: 'readonly',
            scopes: ['orders:read', 'users:read'],
            description: 'Overridden group',
        ));

        $this->assertSame('Overridden read orders', $registry->get('orders:read')?->description);
        $this->assertSame('Overridden group', $registry->groups()['readonly']->description);

        // Deleting persisted overrides restores config definitions cleanly
        $this->assertTrue($store->deleteScope('orders:read'));
        $this->assertTrue($store->deleteGroup('readonly'));

        $restoredScope = $registry->get('orders:read');
        $this->assertNotNull($restoredScope);
        $this->assertSame('Config read orders', $restoredScope->description);
        $this->assertSame('orders', $restoredScope->group);
        $this->assertTrue($restoredScope->isDefault);

        $restoredGroup = $registry->groups()['readonly'] ?? null;
        $this->assertNotNull($restoredGroup);
        $this->assertSame('Config read-only group', $restoredGroup->description);
        $this->assertSame(['orders:read'], $restoredGroup->scopes);
    }

    public function test_file_mode_deleting_config_only_scope_or_group_returns_false(): void
    {
        config()->set('sanctum-manager.scopes.definitions', [
            'config:only' => 'Defined only in config',
        ]);
        config()->set('sanctum-manager.scopes.groups', [
            'config_group' => ['config:only'],
        ]);

        /** @var ScopeRegistry $registry */
        $registry = $this->app->make(ScopeRegistryContract::class);
        $registry->loadFromConfig((array) config('sanctum-manager.scopes', []));

        $store = $this->app->make(ScopeStore::class);

        $this->assertFalse($store->deleteScope('config:only'));
        $this->assertFalse($store->deleteGroup('config_group'));

        $this->assertTrue($registry->has('config:only'));
        $this->assertArrayHasKey('config_group', $registry->groups());
    }

    public function test_file_mode_handles_invalid_json_safely(): void
    {
        File::ensureDirectoryExists($this->tempStorageDir);
        File::put($this->tempStoragePath, '{invalid-json');

        $store = $this->app->make(ScopeStore::class);

        $this->assertSame([], $store->allScopes());
        $this->assertSame([], $store->allGroups());
    }

    public function test_auto_mode_uses_database_driver_when_tables_are_migrated(): void
    {
        $this->enableScopesMigrations();

        $store = $this->app->make(ScopeStore::class);

        $this->assertSame('database', $store->activeDriver());
        $this->assertTrue($store->usesDatabase());
    }

    public function test_database_mode_saves_deletes_and_syncs_scopes(): void
    {
        $this->enableScopesMigrations();

        $registry = $this->app->make(ScopeRegistryContract::class);
        $store = $this->app->make(ScopeStore::class);

        $scope = new ScopeDefinition(
            name: 'billing:manage',
            description: 'Manage billing settings',
            group: 'billing',
            implies: ['billing:read'],
            isDefault: false,
            isDeprecated: false,
        );

        $store->saveScope($scope);

        $this->assertDatabaseHas('sanctum_scopes', [
            'name' => 'billing:manage',
            'description' => 'Manage billing settings',
            'group' => 'billing',
            'is_default' => false,
            'is_deprecated' => false,
        ]);

        $model = SanctumScope::query()->where('name', 'billing:manage')->first();
        $this->assertNotNull($model);
        $this->assertSame(['billing:read'], $model->toDefinition()->implies);

        $this->assertSame(['billing:manage'], $store->persistedScopeNames());
        $this->assertTrue($registry->has('billing:manage'));

        // Verify persistence survives syncToRegistry() on a fresh ScopeRegistry
        $freshRegistry = new ScopeRegistry;
        $store->syncToRegistry($freshRegistry);

        $synced = $freshRegistry->get('billing:manage');
        $this->assertNotNull($synced);
        $this->assertSame('Manage billing settings', $synced->description);
        $this->assertSame(['billing:read'], $synced->implies);

        // Update via saveScope (upsert)
        $store->saveScope(new ScopeDefinition(
            name: 'billing:manage',
            description: 'Full billing control',
            group: 'billing',
            implies: ['billing:read', 'billing:export'],
            isDefault: true,
            isDeprecated: true,
        ));

        $this->assertDatabaseCount('sanctum_scopes', 1);
        $this->assertSame('Full billing control', $registry->get('billing:manage')?->description);
        $this->assertTrue($registry->get('billing:manage')?->isDefault);
        $this->assertTrue($registry->get('billing:manage')?->isDeprecated);

        // Delete persisted scope
        $this->assertTrue($store->deleteScope('billing:manage'));
        $this->assertDatabaseMissing('sanctum_scopes', ['name' => 'billing:manage']);
        $this->assertFalse($registry->has('billing:manage'));
        $this->assertFalse($store->deleteScope('billing:manage'));
    }

    public function test_database_mode_saves_deletes_and_syncs_groups(): void
    {
        $this->enableScopesMigrations();

        $registry = $this->app->make(ScopeRegistryContract::class);
        $store = $this->app->make(ScopeStore::class);

        $group = new ScopeGroup(
            name: 'admin',
            scopes: ['users:manage', 'billing:manage'],
            description: 'Administrator preset',
        );

        $store->saveGroup($group);

        $this->assertDatabaseHas('sanctum_scope_groups', [
            'name' => 'admin',
            'description' => 'Administrator preset',
        ]);

        $model = SanctumScopeGroup::query()->where('name', 'admin')->first();
        $this->assertNotNull($model);
        $this->assertSame(['users:manage', 'billing:manage'], $model->toGroup()->scopes);

        $this->assertSame(['admin'], $store->persistedGroupNames());
        $this->assertArrayHasKey('admin', $registry->groups());

        // Verify persistence survives syncToRegistry() on a fresh ScopeRegistry
        $freshRegistry = new ScopeRegistry;
        $store->syncToRegistry($freshRegistry);

        $this->assertArrayHasKey('admin', $freshRegistry->groups());
        $this->assertSame('Administrator preset', $freshRegistry->groups()['admin']->description);
        $this->assertSame(['users:manage', 'billing:manage'], $freshRegistry->groups()['admin']->scopes);

        // Delete persisted group
        $this->assertTrue($store->deleteGroup('admin'));
        $this->assertDatabaseMissing('sanctum_scope_groups', ['name' => 'admin']);
        $this->assertArrayNotHasKey('admin', $registry->groups());
        $this->assertFalse($store->deleteGroup('admin'));
    }

    public function test_database_mode_deleting_persisted_override_restores_config_definitions(): void
    {
        $this->enableScopesMigrations();

        config()->set('sanctum-manager.scopes.definitions', [
            'users:read' => 'Config users read',
        ]);
        config()->set('sanctum-manager.scopes.groups', [
            'support' => [
                'description' => 'Config support group',
                'scopes' => ['users:read'],
            ],
        ]);

        /** @var ScopeRegistry $registry */
        $registry = $this->app->make(ScopeRegistryContract::class);
        $registry->loadFromConfig((array) config('sanctum-manager.scopes', []));

        $store = $this->app->make(ScopeStore::class);

        $store->saveScope(new ScopeDefinition(name: 'users:read', description: 'DB users read'));
        $store->saveGroup(new ScopeGroup(name: 'support', scopes: ['users:read', 'orders:read'], description: 'DB support'));

        $this->assertSame('DB users read', $registry->get('users:read')?->description);
        $this->assertSame('DB support', $registry->groups()['support']->description);

        $this->assertTrue($store->deleteScope('users:read'));
        $this->assertTrue($store->deleteGroup('support'));

        $this->assertSame('Config users read', $registry->get('users:read')?->description);
        $this->assertSame('Config support group', $registry->groups()['support']->description);
        $this->assertSame(['users:read'], $registry->groups()['support']->scopes);
    }

    public function test_explicit_file_driver_forces_file_mode_even_when_tables_exist(): void
    {
        $this->enableScopesMigrations();
        config()->set('sanctum-manager.scopes.storage_driver', 'file');

        $store = $this->app->make(ScopeStore::class);

        $this->assertSame('file', $store->activeDriver());
        $this->assertFalse($store->usesDatabase());

        $store->saveScope(new ScopeDefinition(name: 'reports:read', description: 'Read reports'));
        $store->saveGroup(new ScopeGroup(name: 'analyst', scopes: ['reports:read'], description: 'Analyst'));

        $this->assertFileExists($this->tempStoragePath);
        $this->assertDatabaseCount('sanctum_scopes', 0);
        $this->assertDatabaseCount('sanctum_scope_groups', 0);
        $this->assertSame(['reports:read'], $store->persistedScopeNames());
        $this->assertSame(['analyst'], $store->persistedGroupNames());
    }
}
