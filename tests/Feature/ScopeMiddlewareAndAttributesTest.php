<?php

declare(strict_types=1);

namespace SanctumManager\Tests\Feature;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use SanctumManager\Events\TokenScopeDenied;
use SanctumManager\Facades\SanctumManager;
use SanctumManager\Models\PersonalAccessToken;
use SanctumManager\Tests\Fixtures\InvokableScopeController;
use SanctumManager\Tests\Fixtures\SampleScopeController;
use SanctumManager\Tests\TestCase;

class ScopeMiddlewareAndAttributesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        SanctumManager::defineScope('orders:read', 'Read orders', group: 'orders');
        SanctumManager::defineScope('orders:write', 'Write orders', group: 'orders', implies: ['orders:read']);
        SanctumManager::defineScope('orders:cancel', 'Cancel orders', group: 'orders');
        SanctumManager::defineScope('orders:admin', 'Administer orders', group: 'orders', implies: ['orders:read', 'orders:write', 'orders:cancel']);
        SanctumManager::defineScope('billing:charge', 'Charge billing', group: 'billing');
        SanctumManager::defineScope('reports:export', 'Export reports', group: 'reports');

        Route::middleware(['auth:sanctum', 'sanctum.scopes:orders:read,orders:write'])
            ->get('/test-scopes-all', static fn (): JsonResponse => response()->json(['ok' => true]));

        Route::middleware(['auth:sanctum', 'sanctum.scope.any:orders:cancel,orders:admin'])
            ->get('/test-scopes-any', static fn (): JsonResponse => response()->json(['ok' => true]));

        Route::middleware(['auth:sanctum', 'sanctum.attributes'])->group(function (): void {
            Route::get('/attr/orders', [SampleScopeController::class, 'index']);
            Route::post('/attr/orders', [SampleScopeController::class, 'store']);
            Route::delete('/attr/orders', [SampleScopeController::class, 'destroy']);
            Route::get('/attr/reports/export', InvokableScopeController::class);
        });
    }

    public function test_check_scopes_middleware_requires_all_scopes(): void
    {
        Event::fake([TokenScopeDenied::class]);

        $user = $this->createUser();
        $validToken = SanctumManager::issue($user, 'Full Orders', ['orders:read', 'orders:write']);
        $partialToken = SanctumManager::issue($user, 'Read Only', ['orders:read']);

        $this->withToken($validToken->plainTextToken)
            ->getJson('/test-scopes-all')
            ->assertOk();

        Event::assertNotDispatched(TokenScopeDenied::class);

        $this->app['auth']->forgetGuards();

        /** @var PersonalAccessToken $partialAccessToken */
        $partialAccessToken = $partialToken->accessToken;

        $this->withToken($partialToken->plainTextToken)
            ->getJson('/test-scopes-all')
            ->assertStatus(403);

        Event::assertDispatched(
            TokenScopeDenied::class,
            static fn (TokenScopeDenied $event): bool => $event->token->getKey() === $partialAccessToken->getKey()
                && $event->requiredScopes === ['orders:write'],
        );
    }

    public function test_check_scopes_middleware_supports_wildcards_and_implied_scopes(): void
    {
        $user = $this->createUser();

        // Wildcard orders:* satisfies both orders:read and orders:write
        $wildcardToken = SanctumManager::issue($user, 'Wildcard Orders', ['orders:*']);

        $this->withToken($wildcardToken->plainTextToken)
            ->getJson('/test-scopes-all')
            ->assertOk();

        $this->app['auth']->forgetGuards();

        // Implied scope: orders:write implies orders:read, so granting orders:write alone satisfies both
        $impliedToken = SanctumManager::issue($user, 'Implied Orders', ['orders:write']);

        $this->withToken($impliedToken->plainTextToken)
            ->getJson('/test-scopes-all')
            ->assertOk();
    }

    public function test_check_for_any_scope_middleware_matches_any_wildcard_or_implied_and_denies_otherwise(): void
    {
        Event::fake([TokenScopeDenied::class]);

        $user = $this->createUser();

        // Matches first listed scope
        $cancelToken = SanctumManager::issue($user, 'Cancel Token', ['orders:cancel']);
        $this->withToken($cancelToken->plainTextToken)
            ->getJson('/test-scopes-any')
            ->assertOk();

        $this->app['auth']->forgetGuards();

        // Matches wildcard
        $wildcardToken = SanctumManager::issue($user, 'Wildcard Token', ['orders:*']);
        $this->withToken($wildcardToken->plainTextToken)
            ->getJson('/test-scopes-any')
            ->assertOk();

        $this->app['auth']->forgetGuards();

        // Matches via implication (orders:admin implies orders:cancel and is also directly listed)
        SanctumManager::defineScope('super:ops', 'Super ops', implies: ['orders:cancel']);
        $impliedToken = SanctumManager::issue($user, 'Implied Cancel Token', ['super:ops']);
        $this->withToken($impliedToken->plainTextToken)
            ->getJson('/test-scopes-any')
            ->assertOk();

        $this->app['auth']->forgetGuards();

        // Missing all required scopes -> 403 + TokenScopeDenied event
        $readOnlyToken = SanctumManager::issue($user, 'Read Only', ['orders:read']);
        /** @var PersonalAccessToken $readOnlyAccessToken */
        $readOnlyAccessToken = $readOnlyToken->accessToken;

        $this->withToken($readOnlyToken->plainTextToken)
            ->getJson('/test-scopes-any')
            ->assertStatus(403);

        Event::assertDispatched(
            TokenScopeDenied::class,
            static fn (TokenScopeDenied $event): bool => $event->token->getKey() === $readOnlyAccessToken->getKey()
                && $event->requiredScopes === ['orders:cancel', 'orders:admin'],
        );
    }

    public function test_controller_class_level_requires_scope_attribute_on_index(): void
    {
        Event::fake([TokenScopeDenied::class]);

        $user = $this->createUser();
        $allowedToken = SanctumManager::issue($user, 'Orders Reader', ['orders:read']);
        $deniedToken = SanctumManager::issue($user, 'Billing Only', ['billing:charge']);

        $this->withToken($allowedToken->plainTextToken)
            ->getJson('/attr/orders')
            ->assertOk()
            ->assertJson(['action' => 'index']);

        $this->app['auth']->forgetGuards();

        $this->withToken($deniedToken->plainTextToken)
            ->getJson('/attr/orders')
            ->assertStatus(403);

        Event::assertDispatched(
            TokenScopeDenied::class,
            static fn (TokenScopeDenied $event): bool => $event->requiredScopes === ['orders:read'],
        );
    }

    public function test_controller_combines_class_level_and_method_level_requires_scope_on_store(): void
    {
        $user = $this->createUser();

        // Has both class-level (orders:read) and method-level (orders:write, billing:charge)
        $fullToken = SanctumManager::issue($user, 'Full Store', ['orders:read', 'orders:write', 'billing:charge']);
        $this->withToken($fullToken->plainTextToken)
            ->postJson('/attr/orders')
            ->assertOk()
            ->assertJson(['action' => 'store']);

        $this->app['auth']->forgetGuards();

        // Missing billing:charge -> 403
        $missingBilling = SanctumManager::issue($user, 'No Billing', ['orders:read', 'orders:write']);
        $this->withToken($missingBilling->plainTextToken)
            ->postJson('/attr/orders')
            ->assertStatus(403);

        $this->app['auth']->forgetGuards();

        // Missing class-level orders:read (and orders:write) -> 403
        $billingOnly = SanctumManager::issue($user, 'Billing Only', ['billing:charge']);
        $this->withToken($billingOnly->plainTextToken)
            ->postJson('/attr/orders')
            ->assertStatus(403);
    }

    public function test_controller_combines_class_level_requires_scope_and_method_requires_any_scope_on_destroy(): void
    {
        $user = $this->createUser();

        // Has class-level orders:read + one of the RequiresAnyScope scopes (orders:cancel)
        $validToken = SanctumManager::issue($user, 'Cancel Orders', ['orders:read', 'orders:cancel']);
        $this->withToken($validToken->plainTextToken)
            ->deleteJson('/attr/orders')
            ->assertOk()
            ->assertJson(['action' => 'destroy']);

        $this->app['auth']->forgetGuards();

        // Has orders:read only, missing both orders:cancel and orders:admin -> 403
        $readOnly = SanctumManager::issue($user, 'Read Only', ['orders:read']);
        $this->withToken($readOnly->plainTextToken)
            ->deleteJson('/attr/orders')
            ->assertStatus(403);

        $this->app['auth']->forgetGuards();

        // Has orders:cancel only, missing class-level orders:read -> 403
        $cancelOnly = SanctumManager::issue($user, 'Cancel Only', ['orders:cancel']);
        $this->withToken($cancelOnly->plainTextToken)
            ->deleteJson('/attr/orders')
            ->assertStatus(403);
    }

    public function test_invokable_controller_requires_scope_attribute(): void
    {
        Event::fake([TokenScopeDenied::class]);

        $user = $this->createUser();
        $exportToken = SanctumManager::issue($user, 'Exporter', ['reports:export']);
        $otherToken = SanctumManager::issue($user, 'Other', ['orders:read']);

        $this->withToken($exportToken->plainTextToken)
            ->getJson('/attr/reports/export')
            ->assertOk()
            ->assertJson(['action' => 'invoke']);

        $this->app['auth']->forgetGuards();

        $this->withToken($otherToken->plainTextToken)
            ->getJson('/attr/reports/export')
            ->assertStatus(403);

        Event::assertDispatched(
            TokenScopeDenied::class,
            static fn (TokenScopeDenied $event): bool => $event->requiredScopes === ['reports:export'],
        );
    }
}
