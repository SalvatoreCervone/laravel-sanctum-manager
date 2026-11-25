<?php

declare(strict_types=1);

namespace SanctumManager\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use SanctumManager\Contracts\ScopeRegistryContract;
use SanctumManager\Contracts\TokenManagerContract;
use SanctumManager\DataTransferObjects\ScopeDefinition;
use SanctumManager\DataTransferObjects\ScopeGroup;
use SanctumManager\DataTransferObjects\TokenOptions;
use SanctumManager\Exceptions\InvalidScopeException;
use SanctumManager\Exceptions\TokenQuotaExceededException;
use SanctumManager\Exceptions\TokenRevokedException;
use SanctumManager\Http\Middleware\AuthorizeDashboard;
use SanctumManager\Mail\TestNotificationMail;
use SanctumManager\Models\PersonalAccessToken;
use SanctumManager\Models\PersonalAccessTokenLog;
use SanctumManager\Services\ScopeStore;

class DashboardController
{
    public function __construct(
        protected TokenManagerContract $tokenManager,
        protected ScopeRegistryContract $scopeRegistry,
        protected ScopeStore $scopeStore,
    ) {}

    /**
     * Render the main dashboard overview with token listing, filters, stats, and quick actions.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $rawStatus = (string) $request->query('status', 'all');
        $status = in_array($rawStatus, ['all', 'active', 'grace', 'expired', 'revoked'], true)
            ? $rawStatus
            : 'all';

        $query = PersonalAccessToken::query()->with('tokenable')->latest('id');

        if ($search !== '') {
            $query->where(static function (Builder $subQuery) use ($search): void {
                $subQuery->where('name', 'like', "%{$search}%");

                if (is_numeric($search)) {
                    $subQuery->orWhere('id', (int) $search);
                }
            });
        }

        match ($status) {
            'active' => $query->active(),
            'grace' => $this->applyGraceFilter($query),
            'expired' => $query->expired(),
            'revoked' => $query->revoked(),
            default => null,
        };

        $perPage = max(1, (int) config('sanctum-manager.dashboard.per_page', 20));
        $tokens = $query->paginate($perPage)->withQueryString();

        $scopes = $this->scopeRegistry->all();
        $groups = $this->scopeRegistry->groups();

        $graceQuery = PersonalAccessToken::query();
        $this->applyGraceFilter($graceQuery);

        $stats = [
            'total' => PersonalAccessToken::query()->count(),
            'active' => PersonalAccessToken::query()->active()->count(),
            'grace' => $graceQuery->count(),
            'expired' => PersonalAccessToken::query()->expired()->count(),
            'revoked' => PersonalAccessToken::query()->revoked()->count(),
            'scopes' => count($scopes),
            'groups' => count($groups),
        ];

        $users = $this->resolveDashboardUsers();
        $filters = [
            'search' => $search,
            'status' => $status,
        ];
        $storageMode = $this->resolveStorageMode();
        $hasLogsTable = Schema::hasTable('sanctum_token_logs');
        $mailEnabled = (bool) config('sanctum-manager.mail.enabled', false);
        $defaultGraceMinutes = (int) config('sanctum-manager.rotation.grace_period_minutes', 30);

        return $this->renderView('sanctum-manager::dashboard.index', compact(
            'tokens',
            'stats',
            'scopes',
            'groups',
            'users',
            'filters',
            'storageMode',
            'hasLogsTable',
            'mailEnabled',
            'defaultGraceMinutes',
        ));
    }

    /**
     * Issue a new personal access token for the selected user.
     */
    public function issue(Request $request): RedirectResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validate([
            'user' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'scopes' => ['nullable', 'array'],
            'scopes.*' => ['string'],
            'custom_scopes' => ['nullable', 'string'],
            'expires_in_days' => ['nullable', 'integer', 'min:1'],
            'allowed_ips' => ['nullable', 'string'],
            'allowed_origins' => ['nullable', 'string'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'token_prefix' => ['nullable', 'string', 'max:32'],
            'rate_limit_per_minute' => ['nullable', 'integer', 'min:1'],
        ]);

        $userIdentifier = is_string($validated['user'] ?? null) ? trim($validated['user']) : '';
        $name = is_string($validated['name'] ?? null) ? trim($validated['name']) : '';

        $user = $this->resolveUser($userIdentifier);

        if ($user === null) {
            return redirect()
                ->back()
                ->withInput()
                ->with('sanctum_manager_error', __('sanctum-manager::sanctum-manager.flash.user_not_found', ['user' => $userIdentifier]));
        }

        $scopes = [];
        if (isset($validated['scopes']) && is_array($validated['scopes'])) {
            foreach ($validated['scopes'] as $scope) {
                if (is_string($scope) && trim($scope) !== '') {
                    $scopes[] = trim($scope);
                }
            }
        }

        if (isset($validated['custom_scopes']) && is_string($validated['custom_scopes'])) {
            foreach ($this->parseDelimitedList($validated['custom_scopes']) as $customScope) {
                $scopes[] = $customScope;
            }
        }

        $scopes = array_values(array_unique($scopes));

        $options = TokenOptions::make();

        if (isset($validated['expires_in_days']) && is_numeric($validated['expires_in_days'])) {
            $options->expiresInDays((int) $validated['expires_in_days']);
        }

        $allowedIps = isset($validated['allowed_ips']) && is_string($validated['allowed_ips'])
            ? $this->parseDelimitedList($validated['allowed_ips'])
            : [];

        if ($allowedIps !== []) {
            $options->allowIps($allowedIps);
        }

        $allowedOrigins = isset($validated['allowed_origins']) && is_string($validated['allowed_origins'])
            ? $this->parseDelimitedList($validated['allowed_origins'])
            : [];

        if ($allowedOrigins !== []) {
            $options->allowOrigins($allowedOrigins);
        }

        if (isset($validated['max_uses']) && is_numeric($validated['max_uses'])) {
            $options->maxUses((int) $validated['max_uses']);
        }

        if (isset($validated['token_prefix']) && is_string($validated['token_prefix']) && trim($validated['token_prefix']) !== '') {
            $options->withPrefix(trim($validated['token_prefix']));
        }

        if (isset($validated['rate_limit_per_minute']) && is_numeric($validated['rate_limit_per_minute'])) {
            $options->withMetadata([
                'rate_limit_per_minute' => (int) $validated['rate_limit_per_minute'],
            ]);
        }

        try {
            $newAccessToken = $this->tokenManager->issue($user, $name, $scopes, $options);
        } catch (InvalidScopeException|TokenQuotaExceededException $exception) {
            return redirect()
                ->back()
                ->withInput()
                ->with('sanctum_manager_error', $exception->getMessage());
        }

        return redirect()
            ->route('sanctum-manager.dashboard.index')
            ->with('sanctum_manager_status', __('sanctum-manager::sanctum-manager.flash.token_issued', ['name' => $name]))
            ->with('sanctum_manager_plain_token', [
                'name' => $name,
                'token' => $newAccessToken->plainTextToken,
                'action' => 'issued',
                'grace_expires_at' => null,
            ]);
    }

    /**
     * Rotate an existing personal access token with an optional grace period.
     */
    public function rotate(Request $request, int|string $tokenId): RedirectResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validate([
            'grace_period_minutes' => ['nullable', 'integer', 'min:0'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var PersonalAccessToken|null $token */
        $token = PersonalAccessToken::query()->find($tokenId);

        if ($token === null) {
            return redirect()
                ->back()
                ->with('sanctum_manager_error', __('sanctum-manager::sanctum-manager.flash.token_not_found'));
        }

        $graceMinutes = isset($validated['grace_period_minutes']) && is_numeric($validated['grace_period_minutes'])
            ? (int) $validated['grace_period_minutes']
            : null;

        $newName = isset($validated['name']) && is_string($validated['name']) && trim($validated['name']) !== ''
            ? trim($validated['name'])
            : null;

        try {
            $result = $this->tokenManager->rotate($token, $graceMinutes, $newName);
        } catch (TokenRevokedException|RuntimeException $exception) {
            return redirect()
                ->back()
                ->with('sanctum_manager_error', $exception->getMessage());
        }

        /** @var PersonalAccessToken $newAccessToken */
        $newAccessToken = $result->newToken->accessToken;

        return redirect()
            ->route('sanctum-manager.dashboard.index')
            ->with('sanctum_manager_status', __('sanctum-manager::sanctum-manager.flash.token_rotated'))
            ->with('sanctum_manager_plain_token', [
                'name' => $newAccessToken->name,
                'token' => $result->newToken->plainTextToken,
                'action' => 'rotated',
                'grace_expires_at' => $result->graceExpiresAt->toIso8601String(),
            ]);
    }

    /**
     * Update the scopes assigned to an existing active personal access token.
     */
    public function updateScopes(Request $request, int|string $tokenId): RedirectResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validate([
            'scopes' => ['nullable', 'array'],
            'scopes.*' => ['string'],
            'custom_scopes' => ['nullable', 'string'],
        ]);

        /** @var PersonalAccessToken|null $token */
        $token = PersonalAccessToken::query()->find($tokenId);

        if ($token === null) {
            return redirect()
                ->back()
                ->with('sanctum_manager_error', __('sanctum-manager::sanctum-manager.flash.token_not_found'));
        }

        $scopes = [];
        if (isset($validated['scopes']) && is_array($validated['scopes'])) {
            foreach ($validated['scopes'] as $scope) {
                if (is_string($scope) && trim($scope) !== '') {
                    $scopes[] = trim($scope);
                }
            }
        }

        if (isset($validated['custom_scopes']) && is_string($validated['custom_scopes'])) {
            $scopes = array_merge($scopes, $this->parseDelimitedList($validated['custom_scopes']));
        }

        $scopes = array_values(array_unique($scopes));

        if ($scopes === []) {
            return redirect()
                ->back()
                ->withInput()
                ->with('sanctum_manager_error', __('sanctum-manager::sanctum-manager.flash.token_scopes_required'));
        }

        try {
            $this->tokenManager->updateScopes($token, $scopes);
        } catch (InvalidScopeException|TokenRevokedException $exception) {
            return redirect()
                ->back()
                ->withInput()
                ->with('sanctum_manager_error', $exception->getMessage());
        }

        return redirect()
            ->route('sanctum-manager.dashboard.index')
            ->with('sanctum_manager_status', __('sanctum-manager::sanctum-manager.flash.token_scopes_updated', ['name' => $token->name]));
    }

    /**
     * Revoke a single personal access token.
     */
    public function revoke(Request $request, int|string $tokenId): RedirectResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var PersonalAccessToken|null $token */
        $token = PersonalAccessToken::query()->find($tokenId);

        if ($token === null) {
            return redirect()
                ->back()
                ->with('sanctum_manager_error', __('sanctum-manager::sanctum-manager.flash.token_not_found'));
        }

        $reason = isset($validated['reason']) && is_string($validated['reason']) && trim($validated['reason']) !== ''
            ? trim($validated['reason'])
            : null;

        $this->tokenManager->revoke($token, $reason);

        return redirect()
            ->route('sanctum-manager.dashboard.index')
            ->with('sanctum_manager_status', __('sanctum-manager::sanctum-manager.flash.token_revoked', ['name' => $token->name]));
    }

    /**
     * Revoke all active personal access tokens belonging to a specific user.
     */
    public function revokeUserTokens(Request $request): RedirectResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validate([
            'user' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $userIdentifier = is_string($validated['user'] ?? null) ? trim($validated['user']) : '';
        $user = $this->resolveUser($userIdentifier);

        if ($user === null) {
            return redirect()
                ->back()
                ->with('sanctum_manager_error', __('sanctum-manager::sanctum-manager.flash.user_not_found', ['user' => $userIdentifier]));
        }

        $reason = isset($validated['reason']) && is_string($validated['reason']) && trim($validated['reason']) !== ''
            ? trim($validated['reason'])
            : null;

        $count = $this->tokenManager->revokeAll($user, $reason);

        return redirect()
            ->route('sanctum-manager.dashboard.index')
            ->with('sanctum_manager_status', __('sanctum-manager::sanctum-manager.flash.user_tokens_revoked', ['count' => $count]));
    }

    /**
     * Prune expired/revoked tokens and stale audit logs.
     */
    public function prune(Request $request): RedirectResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validate([
            'days' => ['nullable', 'integer', 'min:0'],
            'log_days' => ['nullable', 'integer', 'min:0'],
        ]);

        $days = isset($validated['days']) && is_numeric($validated['days'])
            ? (int) $validated['days']
            : 30;

        $logDays = isset($validated['log_days']) && is_numeric($validated['log_days'])
            ? (int) $validated['log_days']
            : 90;

        $result = $this->tokenManager->prune($days, $logDays);

        return redirect()
            ->route('sanctum-manager.dashboard.index')
            ->with(
                'sanctum_manager_status',
                __('sanctum-manager::sanctum-manager.flash.pruned', [
                    'tokens' => $result['tokens'],
                    'logs' => $result['logs'],
                ]),
            );
    }

    /**
     * Render the registered scopes and scope groups view.
     */
    public function scopes(): View
    {
        $scopes = $this->scopeRegistry->all();
        $groups = $this->scopeRegistry->groups();
        $strictValidation = (bool) config('sanctum-manager.scopes.strict_validation', false);
        $storageMode = $this->resolveStorageMode();
        $hasLogsTable = Schema::hasTable('sanctum_token_logs');
        $mailEnabled = (bool) config('sanctum-manager.mail.enabled', false);
        $scopeStorageDriver = $this->scopeStore->activeDriver();
        $scopeStoragePath = $this->scopeStore->storagePath();
        $persistedScopeNames = $this->scopeStore->persistedScopeNames();
        $persistedGroupNames = $this->scopeStore->persistedGroupNames();

        return $this->renderView('sanctum-manager::dashboard.scopes', compact(
            'scopes',
            'groups',
            'strictValidation',
            'storageMode',
            'hasLogsTable',
            'mailEnabled',
            'scopeStorageDriver',
            'scopeStoragePath',
            'persistedScopeNames',
            'persistedGroupNames',
        ));
    }

    /**
     * Create or update a persisted scope definition from the dashboard.
     */
    public function storeScope(Request $request): RedirectResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', 'regex:/^[a-zA-Z0-9:_.*-]+$/'],
            'description' => ['nullable', 'string', 'max:255'],
            'group' => ['nullable', 'string', 'max:100'],
            'implies' => ['nullable', 'array'],
            'implies.*' => ['string', 'max:120'],
            'custom_implies' => ['nullable', 'string', 'max:500'],
            'is_default' => ['nullable', 'boolean'],
            'is_deprecated' => ['nullable', 'boolean'],
        ]);

        $name = is_string($validated['name'] ?? null) ? trim($validated['name']) : '';
        $description = isset($validated['description']) && is_string($validated['description'])
            ? trim($validated['description'])
            : '';
        $group = isset($validated['group']) && is_string($validated['group']) && trim($validated['group']) !== ''
            ? trim($validated['group'])
            : null;

        $implies = [];
        if (isset($validated['implies']) && is_array($validated['implies'])) {
            foreach ($validated['implies'] as $implied) {
                if (is_string($implied) && trim($implied) !== '') {
                    $implies[] = trim($implied);
                }
            }
        }

        if (isset($validated['custom_implies']) && is_string($validated['custom_implies'])) {
            foreach ($this->parseDelimitedList($validated['custom_implies']) as $customImplied) {
                $implies[] = $customImplied;
            }
        }

        $implies = array_values(array_filter(
            array_unique($implies),
            static fn (string $item): bool => $item !== $name,
        ));

        $isDefault = (bool) ($validated['is_default'] ?? false);
        $isDeprecated = (bool) ($validated['is_deprecated'] ?? false);

        $this->scopeStore->saveScope(new ScopeDefinition(
            name: $name,
            description: $description,
            group: $group,
            implies: $implies,
            isDefault: $isDefault,
            isDeprecated: $isDeprecated,
        ));

        return redirect()
            ->route('sanctum-manager.dashboard.scopes')
            ->with('sanctum_manager_status', __('sanctum-manager::sanctum-manager.flash.scope_saved', ['name' => $name]));
    }

    /**
     * Delete a persisted scope definition from the dashboard.
     */
    public function destroyScope(string $scopeName): RedirectResponse
    {
        $deleted = $this->scopeStore->deleteScope($scopeName);

        if (! $deleted) {
            return redirect()
                ->route('sanctum-manager.dashboard.scopes')
                ->with(
                    'sanctum_manager_error',
                    __('sanctum-manager::sanctum-manager.flash.scope_delete_readonly', ['name' => $scopeName]),
                );
        }

        return redirect()
            ->route('sanctum-manager.dashboard.scopes')
            ->with('sanctum_manager_status', __('sanctum-manager::sanctum-manager.flash.scope_deleted', ['name' => $scopeName]));
    }

    /**
     * Create or update a persisted scope group from the dashboard.
     */
    public function storeGroup(Request $request): RedirectResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9:_-]+$/'],
            'description' => ['nullable', 'string', 'max:255'],
            'scopes' => ['nullable', 'array'],
            'scopes.*' => ['string', 'max:120'],
            'custom_scopes' => ['nullable', 'string', 'max:1000'],
        ]);

        $rawName = is_string($validated['name'] ?? null) ? trim($validated['name']) : '';
        $name = ltrim($rawName, '@');
        $description = isset($validated['description']) && is_string($validated['description'])
            ? trim($validated['description'])
            : '';

        $mergedScopes = [];
        if (isset($validated['scopes']) && is_array($validated['scopes'])) {
            foreach ($validated['scopes'] as $scope) {
                if (is_string($scope) && trim($scope) !== '') {
                    $mergedScopes[] = trim($scope);
                }
            }
        }

        if (isset($validated['custom_scopes']) && is_string($validated['custom_scopes'])) {
            foreach ($this->parseDelimitedList($validated['custom_scopes']) as $customScope) {
                $mergedScopes[] = $customScope;
            }
        }

        $mergedScopes = array_values(array_unique($mergedScopes));

        if ($mergedScopes === []) {
            return redirect()
                ->back()
                ->withInput()
                ->with('sanctum_manager_error', __('sanctum-manager::sanctum-manager.flash.group_scopes_required'));
        }

        $this->scopeStore->saveGroup(new ScopeGroup(
            name: $name,
            scopes: $mergedScopes,
            description: $description,
        ));

        return redirect()
            ->route('sanctum-manager.dashboard.scopes')
            ->with('sanctum_manager_status', __('sanctum-manager::sanctum-manager.flash.group_saved', ['name' => $name]));
    }

    /**
     * Delete a persisted scope group from the dashboard.
     */
    public function destroyGroup(string $groupName): RedirectResponse
    {
        $deleted = $this->scopeStore->deleteGroup($groupName);

        if (! $deleted) {
            return redirect()
                ->route('sanctum-manager.dashboard.scopes')
                ->with(
                    'sanctum_manager_error',
                    __('sanctum-manager::sanctum-manager.flash.group_delete_readonly', ['name' => $groupName]),
                );
        }

        return redirect()
            ->route('sanctum-manager.dashboard.scopes')
            ->with('sanctum_manager_status', __('sanctum-manager::sanctum-manager.flash.group_deleted', ['name' => $groupName]));
    }

    /**
     * Render the audit logs view.
     */
    public function logs(): View
    {
        $hasLogsTable = Schema::hasTable('sanctum_token_logs');
        $logs = $hasLogsTable
            ? PersonalAccessTokenLog::query()->with('token')->latest('id')->paginate(25)
            : null;
        $storageMode = $this->resolveStorageMode();
        $mailEnabled = (bool) config('sanctum-manager.mail.enabled', false);
        $rawAuditConfig = config('sanctum-manager.audit', []);
        $auditConfig = is_array($rawAuditConfig) ? $rawAuditConfig : [];

        return $this->renderView('sanctum-manager::dashboard.logs', compact(
            'logs',
            'hasLogsTable',
            'storageMode',
            'mailEnabled',
            'auditConfig',
        ));
    }

    /**
     * Render the mail notification settings and dashboard Gate configuration view.
     */
    public function mailSettings(): View
    {
        $rawMailConfig = config('sanctum-manager.mail', []);
        $mailConfig = is_array($rawMailConfig) ? $rawMailConfig : [];

        $gateConfig = [
            'gate' => (string) config('sanctum-manager.dashboard.gate', 'viewSanctumManager'),
            'allowed_emails' => AuthorizeDashboard::resolveAllowedEmails(),
            'allow_in_local' => (bool) config('sanctum-manager.dashboard.allow_in_local', true),
            'middleware' => config('sanctum-manager.dashboard.middleware', ['web', 'sanctum.dashboard']),
        ];

        $storageMode = $this->resolveStorageMode();
        $hasLogsTable = Schema::hasTable('sanctum_token_logs');
        $mailEnabled = (bool) config('sanctum-manager.mail.enabled', false);

        return $this->renderView('sanctum-manager::dashboard.mail', compact(
            'mailConfig',
            'gateConfig',
            'storageMode',
            'hasLogsTable',
            'mailEnabled',
        ));
    }

    /**
     * Send a test notification email to verify mail transport configuration.
     */
    public function sendTestMail(Request $request): RedirectResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = is_string($validated['email'] ?? null) ? $validated['email'] : '';

        Mail::to($email)->send(new TestNotificationMail($email, now()->toIso8601String()));

        return redirect()
            ->route('sanctum-manager.dashboard.mail')
            ->with('sanctum_manager_status', __('sanctum-manager::sanctum-manager.flash.test_mail_sent', ['email' => $email]));
    }

    /**
     * Render a dashboard Blade view.
     *
     * @param  array<string, mixed>  $data
     */
    protected function renderView(string $viewName, array $data = []): View
    {
        /** @var view-string $viewName */
        return view($viewName, $data);
    }

    /**
     * Apply the dual-mode-safe "in grace period" filter to a PersonalAccessToken query.
     *
     * @param  Builder<PersonalAccessToken>  $query
     */
    protected function applyGraceFilter(Builder $query): void
    {
        if (PersonalAccessToken::usesExtendedColumns()) {
            $query->whereNotNull('grace_expires_at')
                ->where('grace_expires_at', '>', now())
                ->whereNull('revoked_at');

            return;
        }

        $query->where('abilities', 'like', '%__meta:%grace_expires_at%')
            ->where(static function (Builder $subQuery): void {
                $subQuery->whereNull('abilities')
                    ->orWhere('abilities', 'not like', '%__meta:%revoked_at%');
            })
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now());
    }

    /**
     * Resolve the active storage mode string.
     */
    protected function resolveStorageMode(): string
    {
        return PersonalAccessToken::usesExtendedColumns() ? 'extended_columns' : 'zero_migration';
    }

    /**
     * Resolve up to 100 users from the configured auth user model for dashboard selectors.
     *
     * @return array<int, array{id: int|string, name: string, email: string}>
     */
    protected function resolveDashboardUsers(): array
    {
        $modelClass = config('auth.providers.users.model');

        if (! is_string($modelClass) || ! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            return [];
        }

        /** @var Model $prototype */
        $prototype = new $modelClass;

        if (! Schema::hasTable($prototype->getTable())) {
            return [];
        }

        $records = $modelClass::query()->limit(100)->get();
        $users = [];

        foreach ($records as $record) {
            $key = $record->getKey();
            if (! is_int($key) && ! is_string($key)) {
                continue;
            }

            $rawName = $record->getAttribute('name');
            $rawEmail = $record->getAttribute('email');

            $users[] = [
                'id' => $key,
                'name' => is_string($rawName) ? $rawName : (string) $key,
                'email' => is_string($rawEmail) ? $rawEmail : '',
            ];
        }

        return $users;
    }

    /**
     * Resolve a user model instance by numeric ID or email address.
     */
    protected function resolveUser(string $identifier): ?Model
    {
        if ($identifier === '') {
            return null;
        }

        $modelClass = config('auth.providers.users.model');

        if (! is_string($modelClass) || ! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            return null;
        }

        /** @var Model|null $user */
        $user = is_numeric($identifier)
            ? $modelClass::query()->find((int) $identifier)
            : $modelClass::query()->where('email', $identifier)->first();

        return $user;
    }

    /**
     * Split a comma- or newline-delimited string into a deduplicated list of trimmed non-empty strings.
     *
     * @return array<int, string>
     */
    protected function parseDelimitedList(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }

        $parts = preg_split('/[\r\n,]+/', $raw);

        if ($parts === false) {
            return [];
        }

        $items = [];

        foreach ($parts as $part) {
            $trimmed = trim($part);
            if ($trimmed !== '') {
                $items[] = $trimmed;
            }
        }

        return array_values(array_unique($items));
    }
}
