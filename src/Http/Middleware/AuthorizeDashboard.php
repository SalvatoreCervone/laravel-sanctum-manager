<?php

declare(strict_types=1);

namespace SanctumManager\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeDashboard
{
    /**
     * Handle an incoming dashboard request by checking the configured authorization Gate.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $gateName = (string) config('sanctum-manager.dashboard.gate', 'viewSanctumManager');

        if (! Gate::forUser($request->user())->allows($gateName)) {
            abort(403, 'Unauthorized access to Sanctum Manager dashboard.');
        }

        return $next($request);
    }

    /**
     * Resolve and normalize the list of allowed dashboard emails from configuration.
     *
     * Accepts either an array of strings or a comma-separated string, returning
     * a deduplicated list of trimmed, lowercased email addresses.
     *
     * @return array<int, string>
     */
    public static function resolveAllowedEmails(): array
    {
        $configured = config('sanctum-manager.dashboard.allowed_emails', []);

        if (is_string($configured)) {
            $raw = explode(',', $configured);
        } elseif (is_array($configured)) {
            $raw = $configured;
        } else {
            return [];
        }

        $emails = [];

        foreach ($raw as $entry) {
            if (! is_string($entry)) {
                continue;
            }

            $normalized = strtolower(trim($entry));

            if ($normalized !== '') {
                $emails[] = $normalized;
            }
        }

        return array_values(array_unique($emails));
    }
}
