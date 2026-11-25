<?php

declare(strict_types=1);

namespace SanctumManager\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;
use SanctumManager\Scopes\ScopeMatcher;

/**
 * @property int $id
 * @property string $tokenable_type
 * @property int|string $tokenable_id
 * @property string $name
 * @property string $token
 * @property array<int, string>|null $abilities
 * @property Carbon|null $last_used_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $revoked_at
 * @property string|null $revocation_reason
 * @property int|null $rotated_from_id
 * @property Carbon|null $grace_expires_at
 * @property string|null $last_used_ip
 * @property string|null $last_used_user_agent
 * @property int $uses_count
 * @property int|null $max_uses
 * @property array<int, string>|null $allowed_ips
 * @property array<int, string>|null $allowed_origins
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    public const META_PREFIX = '__meta:';

    protected static ?bool $hasExtendedColumnsCache = null;

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'abilities' => 'json',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'grace_expires_at' => 'datetime',
        'allowed_ips' => 'array',
        'allowed_origins' => 'array',
        'metadata' => 'array',
        'uses_count' => 'integer',
        'max_uses' => 'integer',
    ];

    /**
     * Register model lifecycle hooks for cache invalidation.
     */
    protected static function booted(): void
    {
        static::saved(static function (self $token): void {
            $key = $token->getKey();
            if (is_int($key) || is_string($key)) {
                static::forgetCache($key);
            }
        });

        static::deleted(static function (self $token): void {
            $key = $token->getKey();
            if (is_int($key) || is_string($key)) {
                static::forgetCache($key);
                Cache::forget(static::getUsesCacheKey($key));
                Cache::forget("sanctum_mgr:touch:{$key}");
            }
        });
    }

    /**
     * Determine whether optional extended SQL columns are enabled and available.
     */
    public static function usesExtendedColumns(): bool
    {
        $mode = config('sanctum-manager.storage_mode', 'zero_migration');

        if ($mode === 'extended_columns') {
            return true;
        }

        if ($mode === 'zero_migration') {
            return false;
        }

        return static::$hasExtendedColumnsCache ??= Schema::hasColumn(
            static::query()->getModel()->getTable(),
            'revoked_at',
        );
    }

    /**
     * Reset the cached extended-columns detection flag (useful in tests).
     */
    public static function resetExtendedColumnsCache(): void
    {
        static::$hasExtendedColumnsCache = null;
    }

    /**
     * Find the token instance matching the given token, with optional caching.
     *
     * @param  string  $token
     */
    public static function findToken($token): ?static
    {
        if (! (bool) config('sanctum-manager.cache.enabled', false)) {
            /** @var static|null $instance */
            $instance = parent::findToken($token);

            return $instance;
        }

        if (! str_contains($token, '|')) {
            /** @var static|null $instance */
            $instance = parent::findToken($token);

            return $instance;
        }

        $ttl = (int) config('sanctum-manager.cache.ttl_seconds', 60);
        [$id, $plainToken] = explode('|', $token, 2);

        if ($id === '' || $plainToken === '') {
            return null;
        }

        /** @var static|null $instance */
        $instance = Cache::remember(
            static::getCacheKey($id),
            $ttl,
            static fn (): ?static => static::query()->find($id),
        );

        if ($instance !== null && hash_equals($instance->token, hash('sha256', $plainToken))) {
            return $instance;
        }

        return null;
    }

    /**
     * Build the cache key for a given token ID.
     */
    public static function getCacheKey(int|string $id): string
    {
        $prefix = (string) config('sanctum-manager.cache.prefix', 'sanctum_mgr:token:');

        return "{$prefix}{$id}";
    }

    /**
     * Forget the cached token instance for the given token ID.
     */
    public static function forgetCache(int|string $id): void
    {
        Cache::forget(static::getCacheKey($id));
    }

    /**
     * Build the cache key used to track token uses in zero-migration mode.
     */
    public static function getUsesCacheKey(int|string $id): string
    {
        return "sanctum_mgr:uses:{$id}";
    }

    /**
     * Return only real scopes, filtering out any embedded __meta: entry.
     *
     * @return array<int, string>
     */
    public function getEffectiveScopes(): array
    {
        $raw = is_array($this->abilities) ? $this->abilities : [];

        return array_values(array_filter(
            $raw,
            static fn (string $item): bool => ! str_starts_with($item, self::META_PREFIX),
        ));
    }

    /**
     * Read an extended attribute from the SQL column (extended mode) or from embedded __meta: payload.
     */
    public function getMetaAttribute(string $key, mixed $default = null): mixed
    {
        if (static::usesExtendedColumns() && array_key_exists($key, $this->attributes)) {
            return $this->getAttributeValue($key) ?? $default;
        }

        $meta = $this->extractEmbeddedMeta();

        return $meta[$key] ?? $default;
    }

    /**
     * Persist one or more extended attributes on SQL columns (extended mode) or inside the __meta: payload.
     *
     * @param  array<string, mixed>  $values
     */
    public function setMetaAttributes(array $values): self
    {
        if (static::usesExtendedColumns()) {
            $this->forceFill($values);

            return $this;
        }

        $normalized = [];
        foreach ($values as $key => $value) {
            if ($value instanceof DateTimeInterface) {
                $normalized[$key] = Carbon::instance($value)->toIso8601String();
            } else {
                $normalized[$key] = $value;
            }
        }

        $scopes = $this->getEffectiveScopes();
        $meta = array_filter(
            array_merge($this->extractEmbeddedMeta(), $normalized),
            static fn (mixed $value): bool => $value !== null,
        );

        if ($meta !== []) {
            $encoded = json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $scopes[] = self::META_PREFIX.$encoded;
        }

        $this->abilities = $scopes;

        return $this;
    }

    /**
     * Extract the embedded metadata array from the abilities column.
     *
     * @return array<string, mixed>
     */
    protected function extractEmbeddedMeta(): array
    {
        $abilities = is_array($this->abilities) ? $this->abilities : [];

        foreach ($abilities as $item) {
            if (str_starts_with($item, self::META_PREFIX)) {
                $decoded = json_decode(substr($item, strlen(self::META_PREFIX)), true);

                if (is_array($decoded)) {
                    /** @var array<string, mixed> $decoded */
                    return $decoded;
                }

                return [];
            }
        }

        return [];
    }

    /**
     * Get the list of allowed IP addresses or CIDR ranges.
     *
     * @return array<int, string>
     */
    public function getAllowedIps(): array
    {
        $value = $this->getMetaAttribute('allowed_ips', []);

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            $value,
            static fn (mixed $ip): bool => is_string($ip),
        ));
    }

    /**
     * Get the list of allowed Origin/Referer host patterns.
     *
     * @return array<int, string>
     */
    public function getAllowedOrigins(): array
    {
        $value = $this->getMetaAttribute('allowed_origins', []);

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            $value,
            static fn (mixed $origin): bool => is_string($origin),
        ));
    }

    /**
     * Get the maximum allowed uses for this token, if configured.
     */
    public function getMaxUses(): ?int
    {
        $value = $this->getMetaAttribute('max_uses');

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * Get the current number of recorded uses for this token.
     */
    public function getUsesCount(): int
    {
        if (static::usesExtendedColumns()) {
            $value = $this->getMetaAttribute('uses_count', 0);

            return is_numeric($value) ? (int) $value : 0;
        }

        $key = $this->getKey();
        if (is_int($key) || is_string($key)) {
            $cached = Cache::get(static::getUsesCacheKey($key));
            if (is_numeric($cached)) {
                return (int) $cached;
            }
        }

        $fallback = $this->getMetaAttribute('uses_count', 0);

        return is_numeric($fallback) ? (int) $fallback : 0;
    }

    /**
     * Get the grace period expiration timestamp, if rotated.
     */
    public function getGraceExpiresAt(): ?Carbon
    {
        return $this->parseMetaDateTime($this->getMetaAttribute('grace_expires_at'));
    }

    /**
     * Get the revocation timestamp, if soft-revoked.
     */
    public function getRevokedAt(): ?Carbon
    {
        return $this->parseMetaDateTime($this->getMetaAttribute('revoked_at'));
    }

    /**
     * Get the revocation reason, if available.
     */
    public function getRevocationReason(): ?string
    {
        $value = $this->getMetaAttribute('revocation_reason');

        return is_string($value) ? $value : null;
    }

    /**
     * Get arbitrary custom metadata attached to the token.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        $value = $this->getMetaAttribute('metadata', []);

        if (! is_array($value)) {
            return [];
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * Determine whether the token has been explicitly revoked.
     */
    public function isRevoked(): bool
    {
        return $this->getRevokedAt() !== null;
    }

    /**
     * Determine whether the token has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Determine whether the token is currently within its post-rotation grace period.
     */
    public function isInGracePeriod(): bool
    {
        $grace = $this->getGraceExpiresAt();

        return $grace !== null && $grace->isFuture();
    }

    /**
     * Determine whether the token has reached its configured maximum uses.
     */
    public function hasReachedMaxUses(): bool
    {
        $maxUses = $this->getMaxUses();

        return $maxUses !== null && $this->getUsesCount() >= $maxUses;
    }

    /**
     * Determine whether the token is currently usable (not revoked, not expired, and below max uses).
     */
    public function isUsable(): bool
    {
        return ! $this->isRevoked()
            && ! $this->isExpired()
            && ! $this->hasReachedMaxUses();
    }

    /**
     * Determine if the token has the given ability using ScopeMatcher.
     *
     * @param  string  $ability
     */
    public function can($ability): bool
    {
        /** @var ScopeMatcher $matcher */
        $matcher = app(ScopeMatcher::class);

        return $matcher->matches($this->getEffectiveScopes(), (string) $ability);
    }

    /**
     * Determine if the token has at least one of the given abilities.
     *
     * @param  array<int, string>  $abilities
     */
    public function canAny(array $abilities): bool
    {
        /** @var ScopeMatcher $matcher */
        $matcher = app(ScopeMatcher::class);

        return $matcher->matchesAny($this->getEffectiveScopes(), $abilities);
    }

    /**
     * Determine if the token has all of the given abilities.
     *
     * @param  array<int, string>  $abilities
     */
    public function canAll(array $abilities): bool
    {
        /** @var ScopeMatcher $matcher */
        $matcher = app(ScopeMatcher::class);

        return $matcher->matchesAll($this->getEffectiveScopes(), $abilities);
    }

    /**
     * Determine if the token is missing the given ability.
     *
     * @param  string  $ability
     */
    public function cannot($ability): bool
    {
        return ! $this->can($ability);
    }

    /**
     * Scope query to active (non-expired, non-revoked) tokens.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        $query->where(static function (Builder $subQuery): void {
            $subQuery->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });

        if (static::usesExtendedColumns()) {
            return $query->whereNull('revoked_at');
        }

        return $query->where(static function (Builder $subQuery): void {
            $subQuery->whereNull('abilities')
                ->orWhere('abilities', 'not like', '%__meta:%revoked_at%');
        });
    }

    /**
     * Scope query to expired tokens.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }

    /**
     * Scope query to revoked tokens.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeRevoked(Builder $query): Builder
    {
        if (static::usesExtendedColumns()) {
            return $query->whereNotNull('revoked_at');
        }

        return $query->where('abilities', 'like', '%__meta:%revoked_at%');
    }

    /**
     * Scope query to tokens that explicitly contain the given scope in abilities.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithScope(Builder $query, string $scope): Builder
    {
        return $query->whereJsonContains('abilities', $scope);
    }

    /**
     * Get the audit log entries associated with this token (available when extended log table is migrated).
     *
     * @return HasMany<PersonalAccessTokenLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(PersonalAccessTokenLog::class, 'personal_access_token_id');
    }

    /**
     * Parse a mixed date/time value from metadata or column into a Carbon instance.
     */
    protected function parseMetaDateTime(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_string($value) && $value !== '') {
            return Carbon::parse($value);
        }

        return null;
    }
}
