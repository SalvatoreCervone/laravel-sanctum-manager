<?php

declare(strict_types=1);

namespace SanctumManager\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use SanctumManager\Contracts\ScopeRegistryContract;
use SanctumManager\Contracts\TokenUsageLoggerContract;
use SanctumManager\Events\TokenIpDenied;
use SanctumManager\Exceptions\TokenRevokedException;
use SanctumManager\Exceptions\UnauthorizedTokenIpException;
use SanctumManager\Exceptions\UnauthorizedTokenOriginException;
use SanctumManager\Models\PersonalAccessToken;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

class EnforceTokenConstraints
{
    public function __construct(
        protected TokenUsageLoggerContract $usageLogger,
        protected ScopeRegistryContract $scopeRegistry,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = ($user !== null && method_exists($user, 'currentAccessToken'))
            ? $user->currentAccessToken()
            : null;

        if (! $token instanceof PersonalAccessToken) {
            return $next($request);
        }

        if ($token->isRevoked()) {
            throw new TokenRevokedException('This token has been revoked.');
        }

        $graceExpiresAt = $token->getGraceExpiresAt();
        if ($graceExpiresAt !== null && $graceExpiresAt->isPast()) {
            throw new TokenRevokedException('The grace period for this rotated token has expired.');
        }

        if ((bool) config('sanctum-manager.security.enforce_ip_restrictions', true)) {
            $allowedIps = $token->getAllowedIps();

            if ($allowedIps !== []) {
                $clientIp = (string) $request->ip();

                if ($clientIp === '' || ! IpUtils::checkIp($clientIp, $allowedIps)) {
                    event(new TokenIpDenied($user, $token, $clientIp, $request));

                    throw new UnauthorizedTokenIpException("The IP address [{$clientIp}] is not authorized to use this token.");
                }
            }
        }

        if ((bool) config('sanctum-manager.security.enforce_origin_restrictions', true)) {
            $allowedOrigins = $token->getAllowedOrigins();

            if ($allowedOrigins !== []) {
                $origin = $request->headers->get('Origin') ?? $request->headers->get('Referer');

                if (! $this->isOriginAllowed(is_string($origin) ? $origin : null, $allowedOrigins)) {
                    throw new UnauthorizedTokenOriginException('The request origin is not authorized to use this token.');
                }
            }
        }

        $this->usageLogger->recordUsage($token, $request);

        /** @var Response $response */
        $response = $next($request);

        if ($token->isInGracePeriod() && $graceExpiresAt !== null) {
            $response->headers->set('X-Sanctum-Token-Deprecated', 'true');
            $response->headers->set('X-Sanctum-Token-Grace-Expires', $graceExpiresAt->toIso8601String());
        }

        $deprecatedScopes = $this->findDeprecatedScopes($token->getEffectiveScopes());
        if ($deprecatedScopes !== []) {
            $response->headers->set('X-Sanctum-Scope-Deprecated', implode(', ', $deprecatedScopes));
        }

        return $response;
    }

    /**
     * Determine whether the given Origin/Referer matches any allowed origin pattern.
     *
     * Supports exact hosts, full URLs, and wildcard subdomains (e.g. `*.example.com`).
     *
     * @param  array<int, string>  $allowedOrigins
     */
    protected function isOriginAllowed(?string $origin, array $allowedOrigins): bool
    {
        if ($origin === null || $origin === '') {
            return false;
        }

        $parsedOriginScheme = parse_url($origin, PHP_URL_SCHEME);
        $originScheme = is_string($parsedOriginScheme) && $parsedOriginScheme !== '' ? strtolower($parsedOriginScheme) : null;
        $parsedHost = parse_url($origin, PHP_URL_HOST);
        $host = is_string($parsedHost) && $parsedHost !== '' ? $parsedHost : $origin;

        foreach ($allowedOrigins as $pattern) {
            $parsedPatternScheme = parse_url($pattern, PHP_URL_SCHEME);
            $patternScheme = is_string($parsedPatternScheme) && $parsedPatternScheme !== '' ? strtolower($parsedPatternScheme) : null;

            if ($patternScheme !== null && $originScheme !== $patternScheme) {
                continue;
            }

            $parsedPatternHost = parse_url($pattern, PHP_URL_HOST);
            $patternHost = is_string($parsedPatternHost) && $parsedPatternHost !== '' ? $parsedPatternHost : $pattern;

            $quoted = preg_quote($patternHost, '/');
            $regex = '/^'.str_replace('\*', '[a-zA-Z0-9\-_]+(?:\.[a-zA-Z0-9\-_]+)*', $quoted).'$/i';

            if (preg_match($regex, $host) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return any effective scopes on the token that are marked deprecated in the registry.
     *
     * @param  array<int, string>  $effectiveScopes
     * @return array<int, string>
     */
    protected function findDeprecatedScopes(array $effectiveScopes): array
    {
        $deprecated = [];

        foreach ($effectiveScopes as $scope) {
            $definition = $this->scopeRegistry->get($scope);

            if ($definition !== null && $definition->isDeprecated) {
                $deprecated[] = $scope;
            }
        }

        return array_values(array_unique($deprecated));
    }
}
