<?php

declare(strict_types=1);

namespace SanctumManager\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use SanctumManager\SanctumManagerServiceProvider;
use SanctumManager\Tests\TestCase;

class MigrationSafetyTest extends TestCase
{
    public function test_all_optional_migrations_are_idempotent_when_executed_twice(): void
    {
        $columnsMigration = require __DIR__.'/../../database/migrations/add_manager_columns_to_personal_access_tokens_table.php.stub';
        $logsMigration = require __DIR__.'/../../database/migrations/create_sanctum_token_logs_table.php.stub';
        $scopesMigration = require __DIR__.'/../../database/migrations/create_sanctum_scopes_tables.php.stub';

        // First run
        $columnsMigration->up();
        $logsMigration->up();
        $scopesMigration->up();

        // Second run must NOT throw duplicate column / table already exists errors
        $columnsMigration->up();
        $logsMigration->up();
        $scopesMigration->up();

        $this->assertTrue(Schema::hasColumn('personal_access_tokens', 'revoked_at'));
        $this->assertTrue(Schema::hasColumn('personal_access_tokens', 'metadata'));
        $this->assertTrue(Schema::hasTable('sanctum_token_logs'));
        $this->assertTrue(Schema::hasTable('sanctum_scopes'));
        $this->assertTrue(Schema::hasTable('sanctum_scope_groups'));
    }

    public function test_publishing_migrations_reuses_existing_paths_and_never_duplicates_existing_files(): void
    {
        $migrationsDir = database_path('migrations');
        File::ensureDirectoryExists($migrationsDir);

        $existingColumnsFile = $migrationsDir.'/2025_01_01_000000_add_manager_columns_to_personal_access_tokens_table.php';
        $existingLogsFile = $migrationsDir.'/2025_01_01_000001_create_sanctum_token_logs_table.php';

        File::put($existingColumnsFile, '<?php // existing production migration');
        File::put($existingLogsFile, '<?php // existing production logs migration');

        try {
            // Re-register publishables with existing migrations present
            $provider = new SanctumManagerServiceProvider($this->app);
            $provider->boot();

            $paths = ServiceProvider::pathsToPublish(SanctumManagerServiceProvider::class, 'sanctum-manager-migrations');
            $targets = array_values($paths);

            $this->assertContains($existingColumnsFile, $targets);
            $this->assertContains($existingLogsFile, $targets);

            $this->artisan('vendor:publish', [
                '--tag' => 'sanctum-manager-migrations',
            ])->assertSuccessful();

            // Existing files must remain untouched
            $this->assertSame('<?php // existing production migration', File::get($existingColumnsFile));
            $this->assertSame('<?php // existing production logs migration', File::get($existingLogsFile));

            // No duplicate files for columns or logs
            $this->assertCount(1, File::glob($migrationsDir.'/*_add_manager_columns_to_personal_access_tokens_table.php'));
            $this->assertCount(1, File::glob($migrationsDir.'/*_create_sanctum_token_logs_table.php'));

            // Only the new incremental scopes migration was published
            $scopesFiles = File::glob($migrationsDir.'/*_create_sanctum_scopes_tables.php');
            $this->assertCount(1, $scopesFiles);
        } finally {
            foreach (File::glob($migrationsDir.'/*sanctum*') as $file) {
                File::delete($file);
            }
            foreach (File::glob($migrationsDir.'/*add_manager_columns_to_personal_access_tokens_table.php') as $file) {
                File::delete($file);
            }
        }
    }
}
