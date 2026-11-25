<?php

declare(strict_types=1);

namespace SanctumManager\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\SanctumServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use SanctumManager\Models\PersonalAccessToken;
use SanctumManager\SanctumManagerServiceProvider;
use SanctumManager\Tests\Fixtures\User;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        PersonalAccessToken::resetExtendedColumnsCache();
        $this->setUpDatabase();
    }

    protected function getPackageProviders($app): array
    {
        return [
            SanctumServiceProvider::class,
            SanctumManagerServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('sanctum-manager.storage_mode', 'zero_migration');
        $app['config']->set('sanctum-manager.routes.enabled', true);
    }

    protected function setUpDatabase(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });

        // Load ONLY Laravel Sanctum's native migration (Zero-Migration default)
        $sanctumMigration = require __DIR__.'/../vendor/laravel/sanctum/database/migrations/2019_12_14_000001_create_personal_access_tokens_table.php';
        $sanctumMigration->up();
    }

    protected function enableExtendedMigrations(): void
    {
        $columnsMigration = require __DIR__.'/../database/migrations/add_manager_columns_to_personal_access_tokens_table.php.stub';
        $columnsMigration->up();

        $logsMigration = require __DIR__.'/../database/migrations/create_sanctum_token_logs_table.php.stub';
        $logsMigration->up();

        config()->set('sanctum-manager.storage_mode', 'extended_columns');
        PersonalAccessToken::resetExtendedColumnsCache();
    }

    protected function createUser(string $email = 'salvatore@example.com', string $name = 'Salvatore'): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt('secret'),
        ]);
    }
}
