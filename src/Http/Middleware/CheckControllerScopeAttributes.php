<?php

declare(strict_types=1);

namespace SanctumManager\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Laravel\Sanctum\Contracts\HasAbilities;
use ReflectionClass;
use ReflectionMethod;
use SanctumManager\Attributes\RequiresAnyScope;
use SanctumManager\Attributes\RequiresScope;
use SanctumManager\Events\TokenScopeDenied;
use SanctumManager\Exceptions\MissingScopeException;
use SanctumManager\Models\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class CheckControllerScopeAttributes
{
    /**
     * Enforce #[RequiresScope] and #[RequiresAnyScope] PHP 8 attributes declared
     * on the resolved controller class and action method (including invokable controllers).
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $resolved = $this->resolveControllerAction($request);

        if ($resolved === null) {
            return $next($request);
        }

        [$controllerClass, $methodName] = $resolved;

        $classRef = new ReflectionClass($controllerClass);
        $methodRef = new ReflectionMethod($controllerClass, $methodName);

        $requiresAll = array_merge(
            $classRef->getAttributes(RequiresScope::class),
            $methodRef->getAttributes(RequiresScope::class),
        );

        $requiresAny = array_merge(
            $classRef->getAttributes(RequiresAnyScope::class),
            $methodRef->getAttributes(RequiresAnyScope::class),
        );

        if ($requiresAll === [] && $requiresAny === []) {
            return $next($request);
        }

        $user = $request->user();
        $token = ($user !== null && method_exists($user, 'currentAccessToken'))
            ? $user->currentAccessToken()
            : null;

        if ($user === null || ! $token instanceof HasAbilities) {
            throw new AuthenticationException;
        }

        foreach ($requiresAll as $attribute) {
            /** @var RequiresScope $instance */
            $instance = $attribute->newInstance();
            $missing = [];

            foreach ($instance->scopes as $scope) {
                if (! $token->can($scope)) {
                    $missing[] = $scope;
                }
            }

            if ($missing !== []) {
                if ($token instanceof PersonalAccessToken) {
                    event(new TokenScopeDenied($user, $token, $missing, $request));
                }

                throw new MissingScopeException($missing);
            }
        }

        foreach ($requiresAny as $attribute) {
            /** @var RequiresAnyScope $instance */
            $instance = $attribute->newInstance();

            if ($instance->scopes === []) {
                continue;
            }

            $matched = false;

            foreach ($instance->scopes as $scope) {
                if ($token->can($scope)) {
                    $matched = true;
                    break;
                }
            }

            if (! $matched) {
                if ($token instanceof PersonalAccessToken) {
                    event(new TokenScopeDenied($user, $token, $instance->scopes, $request));
                }

                throw new MissingScopeException($instance->scopes);
            }
        }

        return $next($request);
    }

    /**
     * Resolve the controller class and method name for the current route.
     *
     * @return array{0: class-string, 1: string}|null
     */
    protected function resolveControllerAction(Request $request): ?array
    {
        $route = $request->route();

        if (! $route instanceof Route) {
            return null;
        }

        $uses = $route->getAction('uses');

        if (is_string($uses)) {
            if (str_contains($uses, '@')) {
                [$controllerClass, $methodName] = explode('@', $uses, 2);
            } elseif (class_exists($uses) && method_exists($uses, '__invoke')) {
                $controllerClass = $uses;
                $methodName = '__invoke';
            } else {
                return null;
            }
        } elseif (is_array($uses) && count($uses) === 2 && is_string($uses[0]) && is_string($uses[1])) {
            [$controllerClass, $methodName] = $uses;
        } else {
            return null;
        }

        if (! class_exists($controllerClass) || ! method_exists($controllerClass, $methodName)) {
            return null;
        }

        return [$controllerClass, $methodName];
    }
}
