<?php

declare(strict_types=1);

namespace SanctumManager\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use SanctumManager\Scopes\ScopeMatcher;
use SanctumManager\Scopes\ScopeRegistry;
use SanctumManager\Tests\TestCase;

class ScopeMatcherTest extends TestCase
{
    private ScopeRegistry $registry;

    private ScopeMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new ScopeRegistry;
        $this->matcher = new ScopeMatcher($this->registry);
    }

    #[Test]
    public function it_matches_exact_scopes(): void
    {
        $this->assertTrue($this->matcher->matches(['orders:read'], 'orders:read'));
        $this->assertFalse($this->matcher->matches(['orders:read'], 'orders:write'));
        $this->assertFalse($this->matcher->matches([], 'orders:read'));
    }

    #[Test]
    public function it_matches_global_wildcard(): void
    {
        $this->assertTrue($this->matcher->matches(['*'], 'orders:read'));
        $this->assertTrue($this->matcher->matches(['*'], 'billing:invoices:delete'));
    }

    #[Test]
    public function it_matches_namespace_wildcard_including_nested_scopes(): void
    {
        $this->assertTrue($this->matcher->matches(['orders:*'], 'orders:read'));
        $this->assertTrue($this->matcher->matches(['orders:*'], 'orders:write'));
        $this->assertTrue($this->matcher->matches(['orders:*'], 'orders:items:read'));
        $this->assertFalse($this->matcher->matches(['orders:*'], 'invoices:read'));
        $this->assertFalse($this->matcher->matches(['orders:*'], 'orders'));
    }

    #[Test]
    public function it_matches_action_wildcard(): void
    {
        $this->assertTrue($this->matcher->matches(['*:read'], 'orders:read'));
        $this->assertTrue($this->matcher->matches(['*:read'], 'users:read'));
        $this->assertTrue($this->matcher->matches(['*:read'], 'orders:items:read'));
        $this->assertFalse($this->matcher->matches(['*:read'], 'orders:write'));
    }

    #[Test]
    public function it_matches_middle_wildcard(): void
    {
        $this->assertTrue($this->matcher->matches(['orders:*:read'], 'orders:items:read'));
        $this->assertTrue($this->matcher->matches(['orders:*:read'], 'orders:shipping:address:read'));
        $this->assertFalse($this->matcher->matches(['orders:*:read'], 'orders:items:write'));
        $this->assertFalse($this->matcher->matches(['orders:*:read'], 'orders:read'));
    }

    #[Test]
    public function it_matches_transitive_implies_at_runtime_when_only_top_level_scope_is_stored(): void
    {
        $this->registry
            ->define('orders:read')
            ->define('orders:write', implies: ['orders:read'])
            ->define('orders:admin', implies: ['orders:write']);

        // Token only stores 'orders:admin', yet runtime matcher expands implies chain
        $this->assertTrue($this->matcher->matches(['orders:admin'], 'orders:admin'));
        $this->assertTrue($this->matcher->matches(['orders:admin'], 'orders:write'));
        $this->assertTrue($this->matcher->matches(['orders:admin'], 'orders:read'));
        $this->assertFalse($this->matcher->matches(['orders:admin'], 'billing:read'));
    }

    #[Test]
    public function it_evaluates_matches_all(): void
    {
        $this->registry
            ->define('orders:read')
            ->define('orders:write', implies: ['orders:read']);

        $this->assertTrue($this->matcher->matchesAll(['orders:write', 'users:read'], ['orders:read', 'users:read']));
        $this->assertTrue($this->matcher->matchesAll(['orders:*'], ['orders:read', 'orders:items:write']));
        $this->assertFalse($this->matcher->matchesAll(['orders:read'], ['orders:read', 'orders:write']));
        $this->assertTrue($this->matcher->matchesAll(['orders:read'], []));
    }

    #[Test]
    public function it_evaluates_matches_any(): void
    {
        $this->registry
            ->define('orders:read')
            ->define('orders:write', implies: ['orders:read']);

        $this->assertTrue($this->matcher->matchesAny(['orders:write'], ['billing:read', 'orders:read']));
        $this->assertTrue($this->matcher->matchesAny(['*:read'], ['orders:write', 'invoices:read']));
        $this->assertFalse($this->matcher->matchesAny(['orders:read'], ['orders:write', 'billing:read']));
        $this->assertFalse($this->matcher->matchesAny(['orders:read'], []));
    }
}
