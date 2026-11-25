<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Storage Mode
    |--------------------------------------------------------------------------
    |
    | Determines how extended token metadata (allowed IPs, origins, max uses,
    | revocation status, grace period, custom metadata) is stored:
    |
    | - 'zero_migration'   : Uses Sanctum's native `personal_access_tokens`
    |                        table only, embedding metadata inside the JSON
    |                        `abilities` column and using cache for counters.
    | - 'extended_columns' : Uses dedicated SQL columns added by the optional
    |                        published migration.
    | - 'auto'             : Automatically detects whether the extended columns
    |                        exist in the database schema.
    |
    */
    'storage_mode' => env('SANCTUM_MANAGER_STORAGE_MODE', 'zero_migration'),

    /*
    |--------------------------------------------------------------------------
    | Scopes & Groups
    |--------------------------------------------------------------------------
    |
    | Register application scopes, hierarchical implications, and reusable
    | scope groups. When `strict_validation` is enabled, issuing a token with
    | an unregistered scope will throw an InvalidScopeException.
    |
    */
    'scopes' => [
        'strict_validation' => false,

        /*
         * Dynamic scope & group persistence driver for Dashboard CRUD:
         * - 'auto'     : Uses `sanctum_scopes` / `sanctum_scope_groups` DB tables
         *                if migrated, otherwise falls back to the JSON file store.
         * - 'database' : Always stores dynamic scopes & groups in the database.
         * - 'file'     : Always stores dynamic scopes & groups in the fallback JSON file.
         */
        'storage_driver' => env('SANCTUM_MANAGER_SCOPES_DRIVER', 'auto'),
        'storage_path' => env('SANCTUM_MANAGER_SCOPES_PATH', null),

        /*
         * Example:
         * 'orders:read' => [
         *     'description' => 'Read orders',
         *     'group' => 'orders',
         *     'implies' => [],
         *     'is_default' => false,
         *     'is_deprecated' => false,
         * ],
         */
        'definitions' => [],

        /*
         * Example:
         * 'readonly' => [
         *     'description' => 'Read-only access',
         *     'scopes' => ['orders:read', 'users:read'],
         * ],
         */
        'groups' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Issuance Defaults & Quotas
    |--------------------------------------------------------------------------
    |
    | Configure default expiration, per-user active token quotas, strategy when
    | the quota is exceeded ('throw_exception' or 'revoke_oldest'), and custom
    | plain-text token prefixes for secret scanning compatibility.
    |
    */
    'tokens' => [
        'default_expiration_minutes' => null,
        'max_tokens_per_user' => null,
        'quota_exceeded_strategy' => 'throw_exception',
        'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),
        'hash_prefix' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Rotation
    |--------------------------------------------------------------------------
    |
    | Default grace period (in minutes) during which a rotated token remains
    | valid alongside the newly issued token to prevent in-flight failures.
    |
    */
    'rotation' => [
        'grace_period_minutes' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Constraints
    |--------------------------------------------------------------------------
    |
    | Control soft-revocation retention and enforcement of per-token IP/CIDR
    | and Origin/Referer restrictions.
    |
    */
    'security' => [
        'keep_revoked_tokens' => false,
        'enforce_ip_restrictions' => true,
        'enforce_origin_restrictions' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Default requests per minute allowed for token-based rate limiters.
    |
    */
    'rate_limiting' => [
        'default_per_minute' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Usage Tracking & Audit Logging
    |--------------------------------------------------------------------------
    |
    | Debounce database updates to `last_used_at` via cache to avoid row-lock
    | contention under high load. Optional request logging supports 'log'
    | (Laravel log channel) or 'database' (`sanctum_token_logs` table).
    |
    */
    'audit' => [
        'update_interval_seconds' => 300,
        'log_requests' => false,
        'driver' => 'log',
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Lookup Cache
    |--------------------------------------------------------------------------
    |
    | Optionally cache resolved PersonalAccessToken instances to reduce
    | database lookups on authenticated requests.
    |
    */
    'cache' => [
        'enabled' => false,
        'ttl_seconds' => 60,
        'prefix' => 'sanctum_mgr:token:',
    ],

    /*
    |--------------------------------------------------------------------------
    | Headless API Routes
    |--------------------------------------------------------------------------
    |
    | Optionally expose built-in JSON endpoints for listing scopes and managing
    | the authenticated user's personal access tokens.
    |
    */
    'routes' => [
        'enabled' => false,
        'prefix' => 'api/sanctum-manager',
        'middleware' => ['api', 'auth:sanctum'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Web Dashboard UI & Access Gate
    |--------------------------------------------------------------------------
    |
    | Control the built-in graphical web dashboard for managing tokens, scopes,
    | pruning, audit logs, and mail notifications. Access is strictly protected
    | by a Laravel Gate (default: `viewSanctumManager`).
    |
    | You can either list authorized email addresses in `allowed_emails` (or via
    | the comma-separated SANCTUM_MANAGER_ALLOWED_EMAILS env variable) or define
    | your own `Gate::define('viewSanctumManager', fn ($user) => ...)` in your
    | application's AppServiceProvider.
    |
    */
    'dashboard' => [
        'enabled' => (bool) env('SANCTUM_MANAGER_DASHBOARD_ENABLED', true),
        'prefix' => env('SANCTUM_MANAGER_DASHBOARD_PREFIX', 'sanctum-manager'),
        'domain' => env('SANCTUM_MANAGER_DASHBOARD_DOMAIN', null),
        'middleware' => ['web', 'sanctum.dashboard'],
        'gate' => env('SANCTUM_MANAGER_DASHBOARD_GATE', 'viewSanctumManager'),
        'allowed_emails' => env('SANCTUM_MANAGER_ALLOWED_EMAILS', []),
        'allow_in_local' => (bool) env('SANCTUM_MANAGER_ALLOW_IN_LOCAL', true),
        'per_page' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Mail Notifications
    |--------------------------------------------------------------------------
    |
    | Automatically send email notifications when personal access tokens are
    | issued, revoked, or rotated. Plain-text token secrets are NEVER included
    | in notification emails.
    |
    */
    'mail' => [
        'enabled' => (bool) env('SANCTUM_MANAGER_MAIL_ENABLED', false),
        'events' => [
            'issued' => true,
            'revoked' => true,
            'rotated' => true,
        ],
        'notify_token_owner' => true,
        'recipients' => env('SANCTUM_MANAGER_MAIL_RECIPIENTS', []),
        'from' => [
            'address' => env('SANCTUM_MANAGER_MAIL_FROM_ADDRESS', null),
            'name' => env('SANCTUM_MANAGER_MAIL_FROM_NAME', null),
        ],
        'subject_prefix' => env('SANCTUM_MANAGER_MAIL_SUBJECT_PREFIX', '[Sanctum Manager]'),
        'queue' => (bool) env('SANCTUM_MANAGER_MAIL_QUEUE', false),
        'queue_connection' => env('SANCTUM_MANAGER_MAIL_QUEUE_CONNECTION', null),
        'queue_name' => env('SANCTUM_MANAGER_MAIL_QUEUE_NAME', null),
    ],

];
