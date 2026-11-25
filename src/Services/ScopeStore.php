<?php

declare(strict_types=1);

namespace SanctumManager\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use SanctumManager\Contracts\ScopeRegistryContract;
use SanctumManager\DataTransferObjects\ScopeDefinition;
use SanctumManager\DataTransferObjects\ScopeGroup;
use SanctumManager\Models\SanctumScope;
use SanctumManager\Models\SanctumScopeGroup;
use Throwable;

class ScopeStore
{
    public function __construct(
        protected ScopeRegistryContract $registry,
    ) {}

    /**
     * Resolve the active persistence driver ('database' or 'file').
     */
    public function activeDriver(): string
    {
        $driver = (string) config('sanctum-manager.scopes.storage_driver', 'auto');

        if ($driver === 'database') {
            return 'database';
        }

        if ($driver === 'file') {
            return 'file';
        }

        try {
            if (Schema::hasTable('sanctum_scopes') && Schema::hasTable('sanctum_scope_groups')) {
                return 'database';
            }
        } catch (Throwable) {
            return 'file';
        }

        return 'file';
    }

    /**
     * Determine whether the database driver is currently active.
     */
    public function usesDatabase(): bool
    {
        return $this->activeDriver() === 'database';
    }

    /**
     * Get the absolute path to the JSON fallback store file.
     */
    public function storagePath(): string
    {
        $configured = config('sanctum-manager.scopes.storage_path');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return storage_path('app/sanctum-manager/scopes.json');
    }

    /**
     * Retrieve all persisted scope definitions keyed by name.
     *
     * @return array<string, ScopeDefinition>
     */
    public function allScopes(): array
    {
        if ($this->usesDatabase()) {
            $scopes = [];

            /** @var SanctumScope $record */
            foreach (SanctumScope::query()->orderBy('name')->get() as $record) {
                $definition = $record->toDefinition();
                $scopes[$definition->name] = $definition;
            }

            return $scopes;
        }

        $data = $this->readFileData();
        $scopes = [];

        foreach ($data['scopes'] as $key => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $name = isset($entry['name']) && is_string($entry['name']) && $entry['name'] !== ''
                ? $entry['name']
                : $key;

            if ($name === '') {
                continue;
            }

            $implies = [];
            if (isset($entry['implies']) && is_array($entry['implies'])) {
                $implies = array_values(array_filter(
                    $entry['implies'],
                    static fn (mixed $item): bool => is_string($item),
                ));
            }

            $scopes[$name] = new ScopeDefinition(
                name: $name,
                description: isset($entry['description']) && is_string($entry['description'])
                    ? $entry['description']
                    : '',
                group: isset($entry['group']) && is_string($entry['group']) && $entry['group'] !== ''
                    ? $entry['group']
                    : null,
                implies: $implies,
                isDefault: (bool) ($entry['is_default'] ?? false),
                isDeprecated: (bool) ($entry['is_deprecated'] ?? false),
            );
        }

        ksort($scopes);

        return $scopes;
    }

    /**
     * Retrieve all persisted scope groups keyed by name.
     *
     * @return array<string, ScopeGroup>
     */
    public function allGroups(): array
    {
        if ($this->usesDatabase()) {
            $groups = [];

            /** @var SanctumScopeGroup $record */
            foreach (SanctumScopeGroup::query()->orderBy('name')->get() as $record) {
                $group = $record->toGroup();
                $groups[$group->name] = $group;
            }

            return $groups;
        }

        $data = $this->readFileData();
        $groups = [];

        foreach ($data['groups'] as $key => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $name = isset($entry['name']) && is_string($entry['name']) && $entry['name'] !== ''
                ? $entry['name']
                : $key;

            if ($name === '') {
                continue;
            }

            $groupScopes = [];
            if (isset($entry['scopes']) && is_array($entry['scopes'])) {
                $groupScopes = array_values(array_filter(
                    $entry['scopes'],
                    static fn (mixed $item): bool => is_string($item),
                ));
            }

            $groups[$name] = new ScopeGroup(
                name: $name,
                scopes: $groupScopes,
                description: isset($entry['description']) && is_string($entry['description'])
                    ? $entry['description']
                    : '',
            );
        }

        ksort($groups);

        return $groups;
    }

    /**
     * Retrieve the names of all persisted scopes.
     *
     * @return array<int, string>
     */
    public function persistedScopeNames(): array
    {
        return array_keys($this->allScopes());
    }

    /**
     * Retrieve the names of all persisted scope groups.
     *
     * @return array<int, string>
     */
    public function persistedGroupNames(): array
    {
        return array_keys($this->allGroups());
    }

    /**
     * Persist a scope definition and update the in-memory registry.
     */
    public function saveScope(ScopeDefinition $scope): void
    {
        $implies = array_values($scope->implies);

        if ($this->usesDatabase()) {
            SanctumScope::query()->updateOrCreate(
                ['name' => $scope->name],
                [
                    'description' => $scope->description,
                    'group' => $scope->group,
                    'implies' => $implies,
                    'is_default' => $scope->isDefault,
                    'is_deprecated' => $scope->isDeprecated,
                ],
            );
        } else {
            $data = $this->readFileData();
            $data['scopes'][$scope->name] = [
                'name' => $scope->name,
                'description' => $scope->description,
                'group' => $scope->group,
                'implies' => $implies,
                'is_default' => $scope->isDefault,
                'is_deprecated' => $scope->isDeprecated,
            ];
            $this->writeFileData($data);
        }

        $this->registry->define(
            name: $scope->name,
            description: $scope->description,
            group: $scope->group,
            implies: $implies,
            isDefault: $scope->isDefault,
            isDeprecated: $scope->isDeprecated,
        );
    }

    /**
     * Delete a persisted scope definition and restore any config-defined fallback.
     */
    public function deleteScope(string $name): bool
    {
        if ($this->usesDatabase()) {
            $deleted = SanctumScope::query()->where('name', $name)->delete() > 0;
        } else {
            $data = $this->readFileData();

            if (! array_key_exists($name, $data['scopes'])) {
                return false;
            }

            unset($data['scopes'][$name]);
            $this->writeFileData($data);
            $deleted = true;
        }

        if ($deleted) {
            $this->registry->forget($name);
            $this->restoreScopeFromConfig($name);
        }

        return $deleted;
    }

    /**
     * Persist a scope group and update the in-memory registry.
     */
    public function saveGroup(ScopeGroup $group): void
    {
        $scopes = array_values($group->scopes);

        if ($this->usesDatabase()) {
            SanctumScopeGroup::query()->updateOrCreate(
                ['name' => $group->name],
                [
                    'description' => $group->description,
                    'scopes' => $scopes,
                ],
            );
        } else {
            $data = $this->readFileData();
            $data['groups'][$group->name] = [
                'name' => $group->name,
                'description' => $group->description,
                'scopes' => $scopes,
            ];
            $this->writeFileData($data);
        }

        $this->registry->defineGroup($group->name, $scopes, $group->description);
    }

    /**
     * Delete a persisted scope group and restore any config-defined fallback.
     */
    public function deleteGroup(string $name): bool
    {
        if ($this->usesDatabase()) {
            $deleted = SanctumScopeGroup::query()->where('name', $name)->delete() > 0;
        } else {
            $data = $this->readFileData();

            if (! array_key_exists($name, $data['groups'])) {
                return false;
            }

            unset($data['groups'][$name]);
            $this->writeFileData($data);
            $deleted = true;
        }

        if ($deleted) {
            $this->registry->forgetGroup($name);
            $this->restoreGroupFromConfig($name);
        }

        return $deleted;
    }

    /**
     * Load all persisted scopes and groups into the target registry.
     */
    public function syncToRegistry(?ScopeRegistryContract $registry = null): void
    {
        $target = $registry ?? $this->registry;

        try {
            foreach ($this->allScopes() as $scope) {
                $target->define(
                    name: $scope->name,
                    description: $scope->description,
                    group: $scope->group,
                    implies: array_values($scope->implies),
                    isDefault: $scope->isDefault,
                    isDeprecated: $scope->isDeprecated,
                );
            }

            foreach ($this->allGroups() as $group) {
                $target->defineGroup(
                    name: $group->name,
                    scopes: array_values($group->scopes),
                    description: $group->description,
                );
            }
        } catch (Throwable) {
            // Gracefully ignore persistence errors during early boot or unmigrated CLI runs.
        }
    }

    /**
     * Read and decode the JSON store file safely.
     *
     * @return array{scopes: array<string, mixed>, groups: array<string, mixed>}
     */
    protected function readFileData(): array
    {
        $empty = [
            'scopes' => [],
            'groups' => [],
        ];

        $path = $this->storagePath();

        if (! File::exists($path)) {
            return $empty;
        }

        try {
            $contents = File::get($path);
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return $empty;
        }

        if (! is_array($decoded)) {
            return $empty;
        }

        $scopes = [];
        if (isset($decoded['scopes']) && is_array($decoded['scopes'])) {
            foreach ($decoded['scopes'] as $key => $value) {
                if (is_string($key)) {
                    $scopes[$key] = $value;
                }
            }
        }

        $groups = [];
        if (isset($decoded['groups']) && is_array($decoded['groups'])) {
            foreach ($decoded['groups'] as $key => $value) {
                if (is_string($key)) {
                    $groups[$key] = $value;
                }
            }
        }

        return [
            'scopes' => $scopes,
            'groups' => $groups,
        ];
    }

    /**
     * Encode and write the JSON store file atomically.
     *
     * @param  array{scopes: array<string, mixed>, groups: array<string, mixed>}  $data
     */
    protected function writeFileData(array $data): void
    {
        $path = $this->storagePath();

        File::ensureDirectoryExists(dirname($path));

        $encoded = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        File::put($path, $encoded, true);
    }

    /**
     * Restore a single scope definition from application config if present.
     */
    protected function restoreScopeFromConfig(string $name): void
    {
        $definitions = config('sanctum-manager.scopes.definitions', []);

        if (! is_array($definitions) || ! array_key_exists($name, $definitions)) {
            return;
        }

        $definition = $definitions[$name];

        if (is_string($definition)) {
            $this->registry->define(name: $name, description: $definition);

            return;
        }

        if (! is_array($definition)) {
            return;
        }

        $implies = [];
        if (isset($definition['implies']) && is_array($definition['implies'])) {
            $implies = array_values(array_filter(
                $definition['implies'],
                static fn (mixed $item): bool => is_string($item),
            ));
        }

        $this->registry->define(
            name: $name,
            description: isset($definition['description']) && is_string($definition['description'])
                ? $definition['description']
                : '',
            group: isset($definition['group']) && is_string($definition['group'])
                ? $definition['group']
                : null,
            implies: $implies,
            isDefault: (bool) ($definition['default'] ?? $definition['is_default'] ?? false),
            isDeprecated: (bool) ($definition['deprecated'] ?? $definition['is_deprecated'] ?? false),
        );
    }

    /**
     * Restore a single scope group from application config if present.
     */
    protected function restoreGroupFromConfig(string $name): void
    {
        $groups = config('sanctum-manager.scopes.groups', []);

        if (! is_array($groups) || ! array_key_exists($name, $groups)) {
            return;
        }

        $groupConfig = $groups[$name];

        if (! is_array($groupConfig)) {
            return;
        }

        if (isset($groupConfig['scopes']) && is_array($groupConfig['scopes'])) {
            $groupScopes = array_values(array_filter(
                $groupConfig['scopes'],
                static fn (mixed $item): bool => is_string($item),
            ));
            $description = isset($groupConfig['description']) && is_string($groupConfig['description'])
                ? $groupConfig['description']
                : '';

            $this->registry->defineGroup($name, $groupScopes, $description);

            return;
        }

        $groupScopes = array_values(array_filter(
            $groupConfig,
            static fn (mixed $item): bool => is_string($item),
        ));

        $this->registry->defineGroup($name, $groupScopes);
    }
}
