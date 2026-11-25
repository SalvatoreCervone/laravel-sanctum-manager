<?php

declare(strict_types=1);

namespace SanctumManager\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Laravel\Sanctum\Contracts\HasAbilities;
use SanctumManager\Events\TokenScopeDenied;
use SanctumManager\Exceptions\MissingScopeException;
use SanctumManager\Models\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class CheckScopes
{
    /**
     * Require ALL of the specified scopes on the authenticated token.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$scopes): Response
    {
        $user = $request->user();
        $token = ($user !== null && method_exists($user, 'currentAccessToken'))
            ? $user->currentAccessToken()
            : null;

        if ($user === null || ! $token instanceof HasAbilities) {
            throw new AuthenticationException;
        }

        $missing = [];

        foreach ($scopes as $scope) {
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

        return $next($request);
    }
}
