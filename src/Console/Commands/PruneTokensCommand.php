<?php

declare(strict_types=1);

namespace SanctumManager\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use SanctumManager\Models\PersonalAccessToken;
use SanctumManager\Models\PersonalAccessTokenLog;

/**
 * Prunes expired, revoked, and past-grace rotated tokens as well as old audit logs.
 */
class PruneTokensCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sanctum-manager:prune
                            {--days=30 : Delete expired/revoked tokens older than this many days}
                            {--log-days=90 : Delete audit logs older than this many days}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune expired/revoked personal access tokens and old audit logs';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $daysOption = $this->option('days');
        $logDaysOption = $this->option('log-days');

        if (! is_numeric($daysOption) || (int) $daysOption < 0) {
            $this->components->error('The --days option must be a non-negative integer.');

            return self::FAILURE;
        }

        if (! is_numeric($logDaysOption) || (int) $logDaysOption < 0) {
            $this->components->error('The --log-days option must be a non-negative integer.');

            return self::FAILURE;
        }

        $days = (int) $daysOption;
        $logDays = (int) $logDaysOption;

        $tokenCutoff = Carbon::now()->subDays($days);
        $logCutoff = Carbon::now()->subDays($logDays);

        $pruneQuery = PersonalAccessToken::query()
            ->where(static function (Builder $query) use ($tokenCutoff): void {
                $query->where(static function (Builder $expired) use ($tokenCutoff): void {
                    $expired->whereNotNull('expires_at')
                        ->where('expires_at', '<', $tokenCutoff);
                });

                if (PersonalAccessToken::usesExtendedColumns()) {
                    $query->orWhere(static function (Builder $revoked) use ($tokenCutoff): void {
                        $revoked->whereNotNull('revoked_at')
                            ->where('revoked_at', '<', $tokenCutoff);
                    })->orWhere(static function (Builder $rotated) use ($tokenCutoff): void {
                        $rotated->whereNotNull('grace_expires_at')
                            ->where('grace_expires_at', '<', $tokenCutoff);
                    });
                }
            });

        $prunedIds = (clone $pruneQuery)->pluck('id');

        /** @var int $prunedTokens */
        $prunedTokens = $pruneQuery->delete();

        foreach ($prunedIds as $prunedId) {
            if (is_int($prunedId) || is_string($prunedId)) {
                PersonalAccessToken::forgetCache($prunedId);
            }
        }

        $prunedLogs = 0;

        if (Schema::hasTable('sanctum_token_logs')) {
            /** @var int $prunedLogs */
            $prunedLogs = PersonalAccessTokenLog::query()
                ->where('created_at', '<', $logCutoff)
                ->delete();
        }

        $this->components->info('Sanctum Manager pruning completed.');
        $this->components->twoColumnDetail('Pruned Tokens', (string) $prunedTokens);
        $this->components->twoColumnDetail('Pruned Audit Logs', (string) $prunedLogs);

        return self::SUCCESS;
    }
}
