<?php

declare(strict_types=1);

namespace SanctumManager\Console\Commands;

use Illuminate\Console\Command;
use SanctumManager\Contracts\TokenManagerContract;

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
    public function handle(TokenManagerContract $manager): int
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

        $result = $manager->prune((int) $daysOption, (int) $logDaysOption);

        $this->components->info('Sanctum Manager pruning completed.');
        $this->components->twoColumnDetail('Pruned Tokens', (string) $result['tokens']);
        $this->components->twoColumnDetail('Pruned Audit Logs', (string) $result['logs']);

        return self::SUCCESS;
    }
}
