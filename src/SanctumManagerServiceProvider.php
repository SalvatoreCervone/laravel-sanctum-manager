<?php

declare(strict_types=1);

namespace SanctumManager;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use SanctumManager\Console\Commands\InstallCommand;
use SanctumManager\Console\Commands\IssueTokenCommand;
use SanctumManager\Console\Commands\ListScopesCommand;
use SanctumManager\Console\Commands\PruneTokensCommand;
use SanctumManager\Console\Commands\RevokeTokenCommand;
use SanctumManager\Console\Commands\RotateTokenCommand;
use SanctumManager\Contracts\ScopeRegistryContract;
use SanctumManager\Contracts\TokenManagerContract;
use SanctumManager\Contracts\TokenUsageLoggerContract;
use SanctumManager\Events\TokenIssued;
use SanctumManager\Events\TokenRevoked;
use SanctumManager\Events\TokenRotated;
use SanctumManager\Http\Middleware\AuthorizeDashboard;
use SanctumManager\Http\Middleware\CheckControllerScopeAttributes;
use SanctumManager\Http\Middleware\CheckForAnyScope;
use SanctumManager\Http\Middleware\CheckScopes;
use SanctumManager\Http\Middleware\EnforceTokenConstraints;
use SanctumManager\Listeners\SendTokenLifecycleMail;
use SanctumManager\Models\PersonalAccessToken;
use SanctumManager\Scopes\ScopeMatcher;
use SanctumManager\Scopes\ScopeRegistry;
use SanctumManager\Services\DebouncedUsageLogger;
use SanctumManager\Services\TokenLifecycleService;

class SanctumManagerServiceProvider extends ServiceProvider
{
    /**
     * Register package bindings and configuration.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sanctum-manager.php', 'sanctum-manager');

        $this->app->singleton(ScopeRegistryContract::class, function (): ScopeRegistry {
            $registry = new ScopeRegistry;
            $scopesConfig = config('sanctum-manager.scopes', []);

            if (is_array($scopesConfig)) {
                /** @var array<string, mixed> $scopesConfig */
                $registry->loadFromConfig($scopesConfig);
            }

            return $registry;
        });

        $this->app->alias(ScopeRegistryContract::class, ScopeRegistry::class);

        $this->app->singleton(ScopeMatcher::class, function ($app): ScopeMatcher {
            return new ScopeMatcher($app->make(ScopeRegistryContract::class));
        });

        $this->app->singleton(DebouncedUsageLogger::class);
        $this->app->alias(DebouncedUsageLogger::class, TokenUsageLoggerContract::class);

        $this->app->singleton(TokenLifecycleService::class);
        $this->app->alias(TokenLifecycleService::class, TokenManagerContract::class);

        $this->app->singleton(SanctumManager::class);
        $this->app->alias(SanctumManager::class, 'sanctum-manager');
    }

    /**
     * Bootstrap package services, middleware, rate limiters, routes, and commands.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'sanctum-manager');

        $this->registerPublishables();
        $this->registerMiddleware();
        $this->registerDashboardGate();
        $this->registerRateLimiter();
        $this->registerRoutes();
        $this->registerDashboardRoutes();
        $this->registerMailListeners();
        $this->registerCommands();
    }

    /**
     * Register publishable config and opt-in migration stubs.
     *
     * Migrations are intentionally NOT auto-loaded via loadMigrationsFrom()
     * to preserve the Zero-Migration First architecture.
     */
    protected function registerPublishables(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/sanctum-manager.php' => config_path('sanctum-manager.php'),
        ], 'sanctum-manager-config');

        $timestamp1 = date('Y_m_d_His');
        $timestamp2 = date('Y_m_d_His', time() + 1);

        $this->publishes([
            __DIR__.'/../database/migrations/add_manager_columns_to_personal_access_tokens_table.php.stub' => database_path("migrations/{$timestamp1}_add_manager_columns_to_personal_access_tokens_table.php"),
            __DIR__.'/../database/migrations/create_sanctum_token_logs_table.php.stub' => database_path("migrations/{$timestamp2}_create_sanctum_token_logs_table.php"),
        ], 'sanctum-manager-migrations');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/sanctum-manager'),
        ], 'sanctum-manager-views');
    }

    /**
     * Register route middleware aliases.
     */
    protected function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('sanctum.constraints', EnforceTokenConstraints::class);
        $router->aliasMiddleware('sanctum.scopes', CheckScopes::class);
        $router->aliasMiddleware('sanctum.scope.any', CheckForAnyScope::class);
        $router->aliasMiddleware('sanctum.attributes', CheckControllerScopeAttributes::class);
        $router->aliasMiddleware('sanctum.dashboard', AuthorizeDashboard::class);
    }

    /**
     * Register the default authorization gate for the web dashboard.
     *
     * Host applications may override this gate in their AppServiceProvider.
     */
    protected function registerDashboardGate(): void
    {
        $gateName = (string) config('sanctum-manager.dashboard.gate', 'viewSanctumManager');

        if (Gate::has($gateName)) {
            return;
        }

        Gate::define($gateName, function (mixed $user = null): bool {
            if ((bool) config('sanctum-manager.dashboard.allow_in_local', true) && $this->app->environment('local')) {
                return true;
            }

            if ($user === null) {
                return false;
            }

            $allowedEmails = AuthorizeDashboard::resolveAllowedEmails();

            if ($allowedEmails === []) {
                return false;
            }

            $email = is_object($user) && isset($user->email) && is_string($user->email)
                ? strtolower(trim($user->email))
                : null;

            return $email !== null && in_array($email, $allowedEmails, true);
        });
    }

    /**
     * Register the dynamic token-aware rate limiter.
     */
    protected function registerRateLimiter(): void
    {
        RateLimiter::for('sanctum-token', static function (Request $request): Limit {
            $user = $request->user();
            $token = ($user !== null && method_exists($user, 'currentAccessToken'))
                ? $user->currentAccessToken()
                : null;

            $defaultLimit = (int) config('sanctum-manager.rate_limiting.default_per_minute', 60);

            if ($token instanceof PersonalAccessToken) {
                $metadata = $token->getMetadata();
                $customLimit = isset($metadata['rate_limit_per_minute']) && is_numeric($metadata['rate_limit_per_minute'])
                    ? (int) $metadata['rate_limit_per_minute']
                    : $defaultLimit;

                return Limit::perMinute(max(1, $customLimit))
                    ->by('sanctum_token:'.$token->getKey());
            }

            return Limit::perMinute(max(1, $defaultLimit))
                ->by((string) ($request->ip() ?? 'unknown'));
        });
    }

    /**
     * Conditionally register the optional headless API routes.
     */
    protected function registerRoutes(): void
    {
        if (! (bool) config('sanctum-manager.routes.enabled', false)) {
            return;
        }

        $prefix = (string) config('sanctum-manager.routes.prefix', 'api/sanctum-manager');
        $middleware = config('sanctum-manager.routes.middleware', ['api', 'auth:sanctum']);

        Route::group([
            'prefix' => $prefix,
            'middleware' => is_array($middleware) ? $middleware : ['api', 'auth:sanctum'],
        ], function (): void {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        });
    }

    /**
     * Conditionally register the optional graphical web dashboard routes.
     */
    protected function registerDashboardRoutes(): void
    {
        if (! (bool) config('sanctum-manager.dashboard.enabled', true)) {
            return;
        }

        $prefix = (string) config('sanctum-manager.dashboard.prefix', 'sanctum-manager');
        $domain = config('sanctum-manager.dashboard.domain');
        $middleware = config('sanctum-manager.dashboard.middleware', ['web', 'sanctum.dashboard']);

        $attributes = [
            'prefix' => $prefix,
            'middleware' => is_array($middleware) ? $middleware : ['web', 'sanctum.dashboard'],
        ];

        if (is_string($domain) && $domain !== '') {
            $attributes['domain'] = $domain;
        }

        Route::group($attributes, function (): void {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });
    }

    /**
     * Register event listeners for token lifecycle email notifications.
     */
    protected function registerMailListeners(): void
    {
        Event::listen(
            [TokenIssued::class, TokenRevoked::class, TokenRotated::class],
            SendTokenLifecycleMail::class,
        );
    }

    /**
     * Register package Artisan commands when running in console.
     */
    protected function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            InstallCommand::class,
            ListScopesCommand::class,
            IssueTokenCommand::class,
            RevokeTokenCommand::class,
            RotateTokenCommand::class,
            PruneTokensCommand::class,
        ]);
    }
}
