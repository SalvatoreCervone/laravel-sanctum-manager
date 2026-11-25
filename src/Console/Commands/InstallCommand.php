<?php

declare(strict_types=1);

namespace SanctumManager\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\SanctumServiceProvider;
use Throwable;

/**
 * Installs and configures Laravel Sanctum Manager, automatically publishing
 * Laravel Sanctum if its personal_access_tokens migration/table is missing.
 */
class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sanctum-manager:install
                            {--with-migrations : Publish optional extended columns & audit log migrations}
                            {--force : Overwrite existing config/migration files}
                            {--no-migrate : Skip running php artisan migrate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install and configure Laravel Sanctum Manager (and Laravel Sanctum if not yet set up)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->components->info('Installing Laravel Sanctum Manager...');

        $force = (bool) $this->option('force');

        // 1. Ensure Laravel Sanctum is installed / published in the host application
        $this->ensureSanctumIsInstalled();

        // 2. Publish config/sanctum-manager.php
        $this->call('vendor:publish', [
            '--tag' => 'sanctum-manager-config',
            '--force' => $force,
        ]);

        // 3. Optionally publish extended columns & audit log migrations
        $publishOptionalMigrations = (bool) $this->option('with-migrations')
            || $this->components->confirm(
                'Would you like to publish the OPTIONAL extended columns & audit log migrations? (Select No for Zero-Migration mode)',
                default: false
            );

        if ($publishOptionalMigrations) {
            // Never pass --force to migrations so already-published production migrations are never overwritten.
            $this->call('vendor:publish', [
                '--tag' => 'sanctum-manager-migrations',
            ]);

            $this->components->info(
                'Optional migrations published. Set "storage_mode" => "extended_columns" (or "auto") in config/sanctum-manager.php.'
            );
        } else {
            $this->components->info('Zero-Migration mode active: no extra package migrations required.');
        }

        // 4. Optionally run database migrations unless --no-migrate is passed
        if (! (bool) $this->option('no-migrate')) {
            if ($this->components->confirm('Would you like to run "php artisan migrate" now?', default: true)) {
                $this->call('migrate');
            }
        }

        $this->components->info('Laravel Sanctum Manager installed successfully.');

        return self::SUCCESS;
    }

    /**
     * Verify whether Laravel Sanctum's personal_access_tokens table or migration exists,
     * and publish SanctumServiceProvider assets if neither is present.
     */
    protected function ensureSanctumIsInstalled(): void
    {
        $hasTable = false;

        try {
            $hasTable = Schema::hasTable('personal_access_tokens');
        } catch (Throwable) {
            // Database connection may not be configured or reachable yet
        }

        $migrationFiles = File::glob(database_path('migrations/*create_personal_access_tokens_table.php'));
        $hasMigrationFile = $migrationFiles !== [];

        if (! $hasTable && ! $hasMigrationFile) {
            $this->components->warn(
                'Laravel Sanctum does not appear to be set up yet. Publishing Sanctum migration & configuration...'
            );

            $this->call('vendor:publish', [
                '--provider' => SanctumServiceProvider::class,
            ]);
        } else {
            $this->components->twoColumnDetail('Laravel Sanctum', '<fg=green;options=bold>Detected</>');
        }
    }
}
