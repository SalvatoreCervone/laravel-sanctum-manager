<?php

declare(strict_types=1);

namespace SanctumManager\Console\Commands;

use Illuminate\Console\Command;
use SanctumManager\Contracts\ScopeRegistryContract;

/**
 * Displays all registered scopes and scope groups in formatted tables.
 */
class ListScopesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sanctum-manager:scopes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all registered Sanctum Manager scopes and scope groups';

    /**
     * Execute the console command.
     */
    public function handle(ScopeRegistryContract $registry): int
    {
        $scopes = $registry->all();
        $groups = $registry->groups();

        if ($scopes === [] && $groups === []) {
            $this->components->warn('No scopes or scope groups are currently registered.');

            return self::SUCCESS;
        }

        if ($scopes !== []) {
            $this->components->info('Registered Scopes');

            /** @var array<int, array<int, string>> $scopeRows */
            $scopeRows = [];

            foreach ($scopes as $scope) {
                $scopeRows[] = [
                    $scope->name,
                    $scope->group ?? '-',
                    $scope->description !== '' ? $scope->description : '-',
                    $scope->implies !== [] ? implode(', ', $scope->implies) : '-',
                    $scope->isDefault ? 'Yes' : 'No',
                    $scope->isDeprecated ? 'Yes' : 'No',
                ];
            }

            $this->table(
                ['Name', 'Group', 'Description', 'Implies', 'Default', 'Deprecated'],
                $scopeRows
            );
        } else {
            $this->components->warn('No individual scopes are currently registered.');
        }

        if ($groups !== []) {
            $this->newLine();
            $this->components->info('Registered Scope Groups');

            /** @var array<int, array<int, string>> $groupRows */
            $groupRows = [];

            foreach ($groups as $group) {
                $groupRows[] = [
                    '@'.$group->name,
                    $group->description !== '' ? $group->description : '-',
                    $group->scopes !== [] ? implode(', ', $group->scopes) : '-',
                ];
            }

            $this->table(
                ['Group', 'Description', 'Scopes'],
                $groupRows
            );
        }

        return self::SUCCESS;
    }
}
