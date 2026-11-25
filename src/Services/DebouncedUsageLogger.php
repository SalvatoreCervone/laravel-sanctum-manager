<?php

declare(strict_types=1);

namespace SanctumManager\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SanctumManager\Contracts\TokenUsageLoggerContract;
use SanctumManager\Exceptions\TokenMaxUsesExceededException;
use SanctumManager\Models\PersonalAccessToken;
use SanctumManager\Models\PersonalAccessTokenLog;

class DebouncedUsageLogger implements TokenUsageLoggerContract
{
    /**
     * Record token usage, enforcing max-use limits, debounced timestamp updates,
     * and optional request audit logging.
     *
     * @throws TokenMaxUsesExceededException
     */
    public function recordUsage(PersonalAccessToken $token, Request $request): void
    {
        $maxUses = $token->getMaxUses();
        $usesExtended = PersonalAccessToken::usesExtendedColumns();
        $tokenKey = $token->getKey();

        $alreadyUpdatedDb = false;

        // 1. Atomic max_uses enforcement (SQL increment in extended mode, Cache increment in zero-migration mode)
        if ($maxUses !== null) {
            if ($usesExtended) {
                $updated = PersonalAccessToken::query()
                    ->whereKey($tokenKey)
                    ->where('uses_count', '<', $maxUses)
                    ->increment('uses_count', 1, [
                        'last_used_at' => now(),
                        'last_used_ip' => $request->ip(),
                        'last_used_user_agent' => substr((string) $request->userAgent(), 0, 255),
                    ]);

                if ($updated === 0) {
                    throw new TokenMaxUsesExceededException(
                        "Token has reached its maximum number of uses ({$maxUses}).",
                    );
                }

                $alreadyUpdatedDb = true;
            } elseif (is_int($tokenKey) || is_string($tokenKey)) {
                $usesKey = PersonalAccessToken::getUsesCacheKey($tokenKey);
                Cache::add($usesKey, 0);
                $currentUses = (int) Cache::increment($usesKey);

                if ($currentUses > $maxUses) {
                    Cache::decrement($usesKey);

                    throw new TokenMaxUsesExceededException(
                        "Token has reached its maximum number of uses ({$maxUses}).",
                    );
                }
            }
        }

        // 2. Debounced update of last_used_at (and IP/UA/uses_count in extended mode)
        if (! $alreadyUpdatedDb) {
            $interval = (int) config('sanctum-manager.audit.update_interval_seconds', 300);
            $touchKey = "sanctum_mgr:touch:{$tokenKey}";

            if ($interval <= 0 || Cache::add($touchKey, true, $interval)) {
                $updateData = ['last_used_at' => now()];

                if ($usesExtended) {
                    $updateData['last_used_ip'] = $request->ip();
                    $updateData['last_used_user_agent'] = substr((string) $request->userAgent(), 0, 255);

                    PersonalAccessToken::query()
                        ->whereKey($tokenKey)
                        ->increment('uses_count', 1, $updateData);
                } else {
                    PersonalAccessToken::query()
                        ->whereKey($tokenKey)
                        ->update($updateData);
                }
            }
        }

        // 3. Optional request audit log ('database' table or Laravel 'log' channel)
        if ((bool) config('sanctum-manager.audit.log_requests', false)) {
            $driver = (string) config('sanctum-manager.audit.driver', 'log');

            if ($driver === 'database') {
                PersonalAccessTokenLog::query()->create([
                    'personal_access_token_id' => $tokenKey,
                    'ip_address' => $request->ip(),
                    'method' => $request->method(),
                    'path' => substr($request->path(), 0, 255),
                    'user_agent' => substr((string) $request->userAgent(), 0, 255),
                    'created_at' => now(),
                ]);
            } else {
                Log::info('Sanctum token used', [
                    'token_id' => $tokenKey,
                    'ip' => $request->ip(),
                    'method' => $request->method(),
                    'path' => $request->path(),
                ]);
            }
        }
    }
}
