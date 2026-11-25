<?php

declare(strict_types=1);

namespace SanctumManager\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use SanctumManager\DataTransferObjects\ScopeDefinition;
use SanctumManager\DataTransferObjects\ScopeGroup;
use SanctumManager\Exceptions\InvalidScopeException;
use SanctumManager\Scopes\ScopeRegistry;
use SanctumManager\Tests\TestCase;

class ScopeRegistryTest extends TestCase
{
    private ScopeRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new ScopeRegistry;
    }

    #[Test]
    public function it_defines_scopes_groups_defaults_and_deprecated_scopes(): void
    {
        $this->registry
            ->define(
                name: 'orders:read',
                description: 'Read orders',
                group: 'orders',
                isDefault: true,
            )
            ->define(
                name: 'orders:write',
                description: 'Create and update orders',
                group: 'orders',
                implies: ['orders:read'],
            )
            ->define(
                name: 'legacy:export',
                description: 'Deprecated export scope',
                isDeprecated: true,
            )
            ->defineGroup(
                name: 'orders-full',
                scopes: ['orders:read', 'orders:write'],
                description: 'Full order access',
            );

        $this->assertTrue($this->registry->has('orders:read'));
        $this->assertFalse($this->registry->has('nonexistent:scope'));

        $readDef = $this->registry->get('orders:read');
        $this->assertInstanceOf(ScopeDefinition::class, $readDef);
        $this->assertSame('orders:read', $readDef->name);
        $this->assertSame('Read orders', $readDef->description);
        $this->assertSame('orders', $readDef->group);
        $this->assertTrue($readDef->isDefault);
        $this->assertFalse($readDef->isDeprecated);

        $legacyDef = $this->registry->get('legacy:export');
        $this->assertInstanceOf(ScopeDefinition::class, $legacyDef);
        $this->assertTrue($legacyDef->isDeprecated);
        $this->assertFalse($legacyDef->isDefault);

        $this->assertSame(['orders:read'], $this->registry->defaultScopes());
        $this->assertCount(3, $this->registry->all());

        $groups = $this->registry->groups();
        $this->assertArrayHasKey('orders-full', $groups);
        $this->assertInstanceOf(ScopeGroup::class, $groups['orders-full']);
        $this->assertSame(['orders:read', 'orders:write'], $groups['orders-full']->scopes);
        $this->assertSame('Full order access', $groups['orders-full']->description);

        $this->registry->clear();
        $this->assertSame([], $this->registry->all());
        $this->assertSame([], $this->registry->groups());
    }

    #[Test]
    public function it_expands_at_prefixed_and_plain_group_names(): void
    {
        $this->registry
            ->define('orders:read')
            ->define('orders:write', implies: ['orders:read'])
            ->define('invoices:read')
            ->defineGroup('readonly', ['orders:read', 'invoices:read'])
            ->defineGroup('editor', ['orders:write', 'invoices:read']);

        $fromAtPrefix = $this->registry->resolveAndExpand(['@readonly']);
        $this->assertSame(['orders:read', 'invoices:read'], $fromAtPrefix);

        $fromPlainName = $this->registry->resolveAndExpand(['editor']);
        $this->assertSame(['orders:write', 'orders:read', 'invoices:read'], $fromPlainName);
    }

    #[Test]
    public function it_recursively_expands_transitive_implies(): void
    {
        $this->registry
            ->define('scope:c')
            ->define('scope:b', implies: ['scope:c'])
            ->define('scope:a', implies: ['scope:b']);

        $resolved = $this->registry->resolveAndExpand(['scope:a']);

        $this->assertSame(['scope:a', 'scope:b', 'scope:c'], $resolved);
    }

    #[Test]
    public function it_protects_against_circular_implies_dependencies(): void
    {
        $this->registry
            ->define('cycle:a', implies: ['cycle:b'])
            ->define('cycle:b', implies: ['cycle:a']);

        $resolved = $this->registry->resolveAndExpand(['cycle:a']);

        $this->assertSame(['cycle:a', 'cycle:b'], $resolved);
    }

    #[Test]
    public function it_protects_against_deep_circular_implies_dependencies(): void
    {
        $this->registry
            ->define('cycle:a', implies: ['cycle:b'])
            ->define('cycle:b', implies: ['cycle:c'])
            ->define('cycle:c', implies: ['cycle:a']);

        $resolved = $this->registry->resolveAndExpand(['cycle:a']);

        $this->assertSame(['cycle:a', 'cycle:b', 'cycle:c'], $resolved);
    }

    #[Test]
    public function it_throws_invalid_scope_exception_in_strict_mode_for_unregistered_scopes(): void
    {
        $this->registry->define('orders:read');

        $this->expectException(InvalidScopeException::class);
        $this->expectExceptionMessage('The scope [unknown:scope] is not registered in the ScopeRegistry.');

        $this->registry->resolveAndExpand(['orders:read', 'unknown:scope'], strict: true);
    }

    #[Test]
    public function it_allows_global_and_pattern_wildcards_in_strict_mode(): void
    {
        $this->registry->define('orders:read');

        $resolvedGlobal = $this->registry->resolveAndExpand(['*'], strict: true);
        $this->assertSame(['*'], $resolvedGlobal);

        $resolvedPatterns = $this->registry->resolveAndExpand(['orders:*', '*:read', 'orders:read'], strict: true);
        $this->assertSame(['orders:*', '*:read', 'orders:read'], $resolvedPatterns);
    }

    #[Test]
    public function it_allows_unregistered_scopes_in_non_strict_mode(): void
    {
        $this->registry->define('orders:read');

        $resolved = $this->registry->resolveAndExpand(['orders:read', 'custom:unregistered'], strict: false);

        $this->assertSame(['orders:read', 'custom:unregistered'], $resolved);
    }

    #[Test]
    public function it_respects_config_strict_validation_setting_when_strict_parameter_is_null(): void
    {
        config()->set('sanctum-manager.scopes.strict_validation', true);

        $this->expectException(InvalidScopeException::class);

        $this->registry->resolveAndExpand(['not:registered']);
    }

    #[Test]
    public function it_loads_definitions_and_groups_from_config(): void
    {
        $this->registry->loadFromConfig([
            'definitions' => [
                'profile:read' => 'Read user profile',
                'orders:read' => [
                    'description' => 'Read orders',
                    'group' => 'orders',
                    'is_default' => true,
                ],
                'orders:manage' => [
                    'description' => 'Manage orders',
                    'group' => 'orders',
                    'implies' => ['orders:read'],
                    'deprecated' => true,
                ],
            ],
            'groups' => [
                'basic' => ['profile:read', 'orders:read'],
                'admin' => [
                    'description' => 'Admin group',
                    'scopes' => ['orders:manage'],
                ],
            ],
        ]);

        $this->assertTrue($this->registry->has('profile:read'));
        $this->assertSame('Read user profile', $this->registry->get('profile:read')?->description);

        $ordersRead = $this->registry->get('orders:read');
        $this->assertNotNull($ordersRead);
        $this->assertTrue($ordersRead->isDefault);
        $this->assertSame('orders', $ordersRead->group);

        $ordersManage = $this->registry->get('orders:manage');
        $this->assertNotNull($ordersManage);
        $this->assertTrue($ordersManage->isDeprecated);
        $this->assertSame(['orders:read'], $ordersManage->implies);

        $groups = $this->registry->groups();
        $this->assertSame(['profile:read', 'orders:read'], $groups['basic']->scopes);
        $this->assertSame(['orders:manage'], $groups['admin']->scopes);
        $this->assertSame('Admin group', $groups['admin']->description);

        $this->assertSame(['orders:manage', 'orders:read'], $this->registry->resolveAndExpand(['@admin']));
    }
}
