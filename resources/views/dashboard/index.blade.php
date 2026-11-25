@extends('sanctum-manager::dashboard.layout')

@section('title', 'API Tokens')

@section('content')
@php
    $currentStatus = $filters['status'] ?? 'all';
    $currentSearch = $filters['search'] ?? '';
    $statusTabs = [
        'all' => ['label' => 'All', 'count' => $stats['total'] ?? 0],
        'active' => ['label' => 'Active', 'count' => $stats['active'] ?? 0],
        'grace' => ['label' => 'Grace Period', 'count' => $stats['grace'] ?? 0],
        'expired' => ['label' => 'Expired', 'count' => $stats['expired'] ?? 0],
        'revoked' => ['label' => 'Revoked', 'count' => $stats['revoked'] ?? 0],
    ];
@endphp

<div class="sm-page-head">
    <div>
        <h1 class="sm-page-title">API Tokens</h1>
        <p class="sm-page-subtitle">Issue, inspect, rotate, revoke, and prune Laravel Sanctum personal access tokens.</p>
    </div>
    <div class="sm-actions">
        <button type="button" class="sm-btn sm-btn-primary" data-dialog-open="sm-issue-dialog">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="12" y1="5" x2="12" y2="19"/>
                <line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Issue Token
        </button>
        <button type="button" class="sm-btn sm-btn-danger-outline" data-dialog-open="sm-revoke-user-dialog">
            Revoke User Tokens
        </button>
        <button type="button" class="sm-btn" data-dialog-open="sm-prune-dialog">
            Prune Tokens
        </button>
    </div>
</div>

<section class="sm-stats" aria-label="Token statistics">
    <div class="sm-stat-card">
        <div class="sm-stat-label">
            <span>Total Tokens</span>
        </div>
        <div class="sm-stat-value">{{ number_format((int) ($stats['total'] ?? 0)) }}</div>
    </div>
    <div class="sm-stat-card">
        <div class="sm-stat-label">
            <span>Active</span>
            <span class="sm-badge-dot" style="background: var(--success-dot);" aria-hidden="true"></span>
        </div>
        <div class="sm-stat-value">{{ number_format((int) ($stats['active'] ?? 0)) }}</div>
    </div>
    <div class="sm-stat-card">
        <div class="sm-stat-label">
            <span>In Grace</span>
            <span class="sm-badge-dot" style="background: var(--warning-dot);" aria-hidden="true"></span>
        </div>
        <div class="sm-stat-value">{{ number_format((int) ($stats['grace'] ?? 0)) }}</div>
    </div>
    <div class="sm-stat-card">
        <div class="sm-stat-label">
            <span>Expired</span>
            <span class="sm-badge-dot" style="background: var(--neutral-dot);" aria-hidden="true"></span>
        </div>
        <div class="sm-stat-value">{{ number_format((int) ($stats['expired'] ?? 0)) }}</div>
    </div>
    <div class="sm-stat-card">
        <div class="sm-stat-label">
            <span>Revoked</span>
            <span class="sm-badge-dot" style="background: var(--danger-dot);" aria-hidden="true"></span>
        </div>
        <div class="sm-stat-value">{{ number_format((int) ($stats['revoked'] ?? 0)) }}</div>
    </div>
    <div class="sm-stat-card">
        <div class="sm-stat-label">
            <span>Scopes / Groups</span>
        </div>
        <div class="sm-stat-value">
            {{ number_format((int) ($stats['scopes'] ?? 0)) }}
            <span style="font-size:15px;font-weight:500;color:var(--text-muted);">/ {{ number_format((int) ($stats['groups'] ?? 0)) }}</span>
        </div>
    </div>
</section>

<section class="sm-card" aria-label="Tokens table">
    <div class="sm-toolbar">
        <div class="sm-tabs" role="tablist" aria-label="Filter tokens by status">
            @foreach ($statusTabs as $statusKey => $tab)
                @php
                    $tabQuery = [];
                    if ($statusKey !== 'all') {
                        $tabQuery['status'] = $statusKey;
                    }
                    if ($currentSearch !== '') {
                        $tabQuery['search'] = $currentSearch;
                    }
                @endphp
                <a href="{{ route('sanctum-manager.dashboard.index', $tabQuery) }}"
                   class="sm-tab {{ $currentStatus === $statusKey ? 'is-active' : '' }}"
                   role="tab"
                   aria-selected="{{ $currentStatus === $statusKey ? 'true' : 'false' }}">
                    {{ $tab['label'] }}
                    <span style="opacity:0.7;margin-left:3px;">({{ $tab['count'] }})</span>
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('sanctum-manager.dashboard.index') }}" class="sm-search-form" role="search">
            @if ($currentStatus !== 'all')
                <input type="hidden" name="status" value="{{ $currentStatus }}">
            @endif
            <label for="sm-token-search" class="sr-only">Search tokens</label>
            <input type="search"
                   id="sm-token-search"
                   name="search"
                   class="sm-input"
                   value="{{ $currentSearch }}"
                   placeholder="Search by name, scope, or ID...">
            <button type="submit" class="sm-btn">Search</button>
            @if ($currentSearch !== '')
                <a href="{{ route('sanctum-manager.dashboard.index', $currentStatus !== 'all' ? ['status' => $currentStatus] : []) }}" class="sm-btn">Clear</a>
            @endif
        </form>
    </div>

    @if ($tokens->count() === 0)
        <div class="sm-empty">
            <div class="sm-empty-icon" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>
                </svg>
            </div>
            <h3>No tokens found</h3>
            <p>
                @if ($currentSearch !== '' || $currentStatus !== 'all')
                    No tokens match your current filter criteria. Try clearing your search or switching status tabs.
                @else
                    No personal access tokens have been issued yet. Click <strong>Issue Token</strong> to create your first token.
                @endif
            </p>
        </div>
    @else
        <div class="sm-table-wrap">
            <table class="sm-table">
                <thead>
                    <tr>
                        <th>Token</th>
                        <th>Owner</th>
                        <th>Status</th>
                        <th>Scopes</th>
                        <th>Constraints &amp; Usage</th>
                        <th>Timestamps</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tokens as $token)
                        @php
                            $effectiveScopes = $token->getEffectiveScopes();
                            $allowedIps = $token->getAllowedIps();
                            $allowedOrigins = $token->getAllowedOrigins();
                            $maxUses = $token->getMaxUses();
                            $usesCount = $token->getUsesCount();
                            $isRevoked = $token->isRevoked();
                            $isExpired = $token->isExpired();
                            $isInGrace = $token->isInGracePeriod();
                            $graceExpiresAt = $token->getGraceExpiresAt();
                            $revocationReason = $token->getRevocationReason();
                            $owner = $token->tokenable;
                            $ownerName = $owner?->name ?? null;
                            $ownerEmail = $owner?->email ?? null;
                            $canRotate = ! $isRevoked && ! $isExpired;
                        @endphp
                        <tr>
                            <td>
                                <div class="sm-cell-title">{{ $token->name }}</div>
                                <div class="sm-cell-sub mono">#{{ $token->getKey() }}</div>
                            </td>
                            <td>
                                @if ($owner)
                                    <div class="sm-cell-title">{{ $ownerName ?: 'User #'.$token->tokenable_id }}</div>
                                    <div class="sm-cell-sub mono">{{ $ownerEmail ?: $token->tokenable_type.' #'.$token->tokenable_id }}</div>
                                @else
                                    <div class="sm-cell-title mono">#{{ $token->tokenable_id }}</div>
                                    <div class="sm-cell-sub">{{ class_basename((string) $token->tokenable_type) }}</div>
                                @endif
                            </td>
                            <td>
                                @if ($isRevoked)
                                    <span class="sm-badge sm-badge-danger">
                                        <span class="sm-badge-dot" aria-hidden="true"></span>
                                        Revoked
                                    </span>
                                    @if ($revocationReason)
                                        <div class="sm-cell-sub" title="{{ $revocationReason }}">{{ \Illuminate\Support\Str::limit($revocationReason, 36) }}</div>
                                    @endif
                                @elseif ($isExpired)
                                    <span class="sm-badge sm-badge-neutral">
                                        <span class="sm-badge-dot" aria-hidden="true"></span>
                                        Expired
                                    </span>
                                @elseif ($isInGrace)
                                    <span class="sm-badge sm-badge-warning">
                                        <span class="sm-badge-dot" aria-hidden="true"></span>
                                        Grace Period
                                    </span>
                                    @if ($graceExpiresAt)
                                        <div class="sm-cell-sub mono">Ends {{ $graceExpiresAt->diffForHumans() }}</div>
                                    @endif
                                @else
                                    <span class="sm-badge sm-badge-success">
                                        <span class="sm-badge-dot" aria-hidden="true"></span>
                                        Active
                                    </span>
                                @endif
                            </td>
                            <td style="max-width: 240px;">
                                @if (count($effectiveScopes) > 0)
                                    <div class="sm-chips">
                                        @foreach ($effectiveScopes as $scopeName)
                                            <span class="sm-chip {{ $scopeName === '*' ? 'sm-chip-accent' : '' }}">{{ $scopeName }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="sm-cell-sub">None</span>
                                @endif
                            </td>
                            <td>
                                <div class="sm-chips" style="flex-direction:column;align-items:flex-start;gap:4px;">
                                    <span class="sm-cell-sub">
                                        Uses: <strong class="mono">{{ number_format($usesCount) }}@if($maxUses !== null) / {{ number_format($maxUses) }}@endif</strong>
                                    </span>
                                    @if (count($allowedIps) > 0)
                                        <span class="sm-chip" title="{{ implode(', ', $allowedIps) }}">
                                            IPs: {{ implode(', ', array_slice($allowedIps, 0, 2)) }}@if(count($allowedIps) > 2) +{{ count($allowedIps) - 2 }}@endif
                                        </span>
                                    @endif
                                    @if (count($allowedOrigins) > 0)
                                        <span class="sm-chip" title="{{ implode(', ', $allowedOrigins) }}">
                                            Origins: {{ implode(', ', array_slice($allowedOrigins, 0, 1)) }}@if(count($allowedOrigins) > 1) +{{ count($allowedOrigins) - 1 }}@endif
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td style="white-space:nowrap;">
                                <div class="sm-cell-sub">
                                    Created: <span class="mono" title="{{ $token->created_at?->toIso8601String() }}">{{ $token->created_at?->diffForHumans() ?? '—' }}</span>
                                </div>
                                <div class="sm-cell-sub">
                                    Last used: <span class="mono" title="{{ $token->last_used_at?->toIso8601String() }}">{{ $token->last_used_at?->diffForHumans() ?? 'Never' }}</span>
                                </div>
                                <div class="sm-cell-sub">
                                    Expires: <span class="mono" title="{{ $token->expires_at?->toIso8601String() }}">{{ $token->expires_at ? $token->expires_at->format('Y-m-d H:i') : 'Never' }}</span>
                                </div>
                            </td>
                            <td style="text-align:right;white-space:nowrap;">
                                <div style="display:inline-flex;gap:6px;align-items:center;">
                                    <button type="button"
                                            class="sm-btn sm-btn-sm"
                                            data-dialog-open="sm-rotate-dialog"
                                            data-form-action="{{ route('sanctum-manager.dashboard.tokens.rotate', ['tokenId' => $token->getKey()]) }}"
                                            data-token-name="{{ $token->name }}"
                                            data-token-id="{{ $token->getKey() }}"
                                            @if(! $canRotate) disabled title="Cannot rotate an expired or revoked token" @endif>
                                        Rotate
                                    </button>
                                    <button type="button"
                                            class="sm-btn sm-btn-sm sm-btn-danger-outline"
                                            data-dialog-open="sm-revoke-dialog"
                                            data-form-action="{{ route('sanctum-manager.dashboard.tokens.revoke', ['tokenId' => $token->getKey()]) }}"
                                            data-token-name="{{ $token->name }}"
                                            data-token-id="{{ $token->getKey() }}">
                                        Revoke
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($tokens->hasPages())
            <div class="sm-pagination" role="navigation" aria-label="Tokens pagination">
                <div>
                    Showing <strong>{{ $tokens->firstItem() }}</strong> to <strong>{{ $tokens->lastItem() }}</strong> of <strong>{{ $tokens->total() }}</strong> tokens
                </div>
                <div class="sm-pagination-links">
                    <a href="{{ $tokens->previousPageUrl() ?: '#' }}"
                       class="sm-page-link {{ $tokens->onFirstPage() ? 'is-disabled' : '' }}"
                       @if($tokens->onFirstPage()) aria-disabled="true" tabindex="-1" @endif>
                        Prev
                    </a>
                    @php
                        $startPage = max(1, $tokens->currentPage() - 2);
                        $endPage = min($tokens->lastPage(), $tokens->currentPage() + 2);
                    @endphp
                    @for ($page = $startPage; $page <= $endPage; $page++)
                        <a href="{{ $tokens->url($page) }}"
                           class="sm-page-link {{ $page === $tokens->currentPage() ? 'is-active' : '' }}"
                           @if($page === $tokens->currentPage()) aria-current="page" @endif>
                            {{ $page }}
                        </a>
                    @endfor
                    <a href="{{ $tokens->nextPageUrl() ?: '#' }}"
                       class="sm-page-link {{ ! $tokens->hasMorePages() ? 'is-disabled' : '' }}"
                       @if(! $tokens->hasMorePages()) aria-disabled="true" tabindex="-1" @endif>
                        Next
                    </a>
                </div>
            </div>
        @endif
    @endif
</section>

{{-- Shared Users Datalist --}}
<datalist id="sm-users-list">
    @foreach ($users as $userItem)
        <option value="{{ $userItem['email'] ?: $userItem['id'] }}">
            {{ $userItem['name'] }} (#{{ $userItem['id'] }})
        </option>
    @endforeach
</datalist>

{{-- 1. Issue Token Dialog --}}
<dialog id="sm-issue-dialog" class="sm-dialog" aria-labelledby="sm-issue-dialog-title">
    <form method="POST" action="{{ route('sanctum-manager.dashboard.tokens.issue') }}" class="sm-dialog-form">
        @csrf
        <div class="sm-dialog-header">
            <h2 class="sm-dialog-title" id="sm-issue-dialog-title">Issue Personal Access Token</h2>
            <button type="button" class="sm-icon-btn" data-dialog-close aria-label="Close modal">&times;</button>
        </div>

        <div class="sm-dialog-body">
            <div class="sm-field-grid">
                <div class="sm-field">
                    <label for="sm-issue-user" class="sm-label">User (ID or Email) <span style="color:var(--danger-dot);">*</span></label>
                    <input type="text"
                           id="sm-issue-user"
                           name="user"
                           list="sm-users-list"
                           class="sm-input"
                           value="{{ old('user') }}"
                           placeholder="user@example.com or 1"
                           required>
                    <p class="sm-help">Pick a user from the list or enter a user ID or email.</p>
                </div>

                <div class="sm-field">
                    <label for="sm-issue-name" class="sm-label">Token Name <span style="color:var(--danger-dot);">*</span></label>
                    <input type="text"
                           id="sm-issue-name"
                           name="name"
                           class="sm-input"
                           value="{{ old('name') }}"
                           placeholder="e.g. CI Deploy Key"
                           required>
                </div>
            </div>

            @if (count($groups) > 0)
                <div class="sm-field">
                    <span class="sm-label">Scope Groups</span>
                    <div class="sm-check-grid">
                        @foreach ($groups as $group)
                            <label class="sm-check-item">
                                <input type="checkbox" name="scopes[]" value="{{ '@' . $group->name }}">
                                <span>
                                    <strong class="mono">{{ '@' . $group->name }}</strong>
                                    @if ($group->description !== '')
                                        <span class="sm-cell-sub" style="display:block;">{{ $group->description }}</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif

            @if (count($scopes) > 0)
                <div class="sm-field">
                    <span class="sm-label">Registered Scopes</span>
                    <div class="sm-check-grid">
                        @foreach ($scopes as $scope)
                            <label class="sm-check-item">
                                <input type="checkbox" name="scopes[]" value="{{ $scope->name }}" @if($scope->isDefault) checked @endif>
                                <span>
                                    <strong class="mono">{{ $scope->name }}</strong>
                                    @if ($scope->isDeprecated)
                                        <span class="sm-badge sm-badge-warning" style="padding:1px 5px;font-size:10px;">Deprecated</span>
                                    @endif
                                    @if ($scope->description !== '')
                                        <span class="sm-cell-sub" style="display:block;">{{ $scope->description }}</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="sm-field">
                <label for="sm-issue-custom-scopes" class="sm-label">Custom Scopes</label>
                <input type="text"
                       id="sm-issue-custom-scopes"
                       name="custom_scopes"
                       class="sm-input mono"
                       value="{{ old('custom_scopes') }}"
                       placeholder="e.g. orders:read, billing:export">
                <p class="sm-help">Optional comma-separated extra scopes (or <code>*</code> for full access).</p>
            </div>

            <div class="sm-field-grid">
                <div class="sm-field">
                    <label for="sm-issue-expires" class="sm-label">Expires In (Days)</label>
                    <input type="number"
                           id="sm-issue-expires"
                           name="expires_in_days"
                           class="sm-input"
                           min="1"
                           value="{{ old('expires_in_days') }}"
                           placeholder="Never">
                </div>

                <div class="sm-field">
                    <label for="sm-issue-max-uses" class="sm-label">Max Uses</label>
                    <input type="number"
                           id="sm-issue-max-uses"
                           name="max_uses"
                           class="sm-input"
                           min="1"
                           value="{{ old('max_uses') }}"
                           placeholder="Unlimited">
                </div>

                <div class="sm-field">
                    <label for="sm-issue-prefix" class="sm-label">Token Prefix</label>
                    <input type="text"
                           id="sm-issue-prefix"
                           name="token_prefix"
                           class="sm-input mono"
                           value="{{ old('token_prefix') }}"
                           placeholder="e.g. sk_live_">
                </div>

                <div class="sm-field">
                    <label for="sm-issue-rate-limit" class="sm-label">Rate Limit (req/min)</label>
                    <input type="number"
                           id="sm-issue-rate-limit"
                           name="rate_limit_per_minute"
                           class="sm-input"
                           min="1"
                           value="{{ old('rate_limit_per_minute') }}"
                           placeholder="Default">
                </div>
            </div>

            <div class="sm-field-grid">
                <div class="sm-field" style="margin-bottom:0;">
                    <label for="sm-issue-ips" class="sm-label">Allowed IPs / CIDRs</label>
                    <input type="text"
                           id="sm-issue-ips"
                           name="allowed_ips"
                           class="sm-input mono"
                           value="{{ old('allowed_ips') }}"
                           placeholder="10.0.0.1, 192.168.1.0/24">
                </div>

                <div class="sm-field" style="margin-bottom:0;">
                    <label for="sm-issue-origins" class="sm-label">Allowed Origins</label>
                    <input type="text"
                           id="sm-issue-origins"
                           name="allowed_origins"
                           class="sm-input mono"
                           value="{{ old('allowed_origins') }}"
                           placeholder="https://app.example.com, *.example.com">
                </div>
            </div>
        </div>

        <div class="sm-dialog-footer">
            <button type="button" class="sm-btn" data-dialog-close>Cancel</button>
            <button type="submit" class="sm-btn sm-btn-primary">Issue Token</button>
        </div>
    </form>
</dialog>

{{-- 2. Rotate Token Dialog --}}
<dialog id="sm-rotate-dialog" class="sm-dialog sm-dialog-sm" aria-labelledby="sm-rotate-dialog-title">
    <form method="POST" action="" class="sm-dialog-form">
        @csrf
        <div class="sm-dialog-header">
            <h2 class="sm-dialog-title" id="sm-rotate-dialog-title">Rotate Token</h2>
            <button type="button" class="sm-icon-btn" data-dialog-close aria-label="Close modal">&times;</button>
        </div>

        <div class="sm-dialog-body">
            <p style="margin:0 0 14px;color:var(--text-secondary);">
                Rotating <strong data-bind-token-name>this token</strong> (<span class="mono" data-bind-token-id></span>) issues a brand-new secret while keeping the current token valid during the grace period.
            </p>

            <div class="sm-field">
                <label for="sm-rotate-grace" class="sm-label">Grace Period (Minutes)</label>
                <input type="number"
                       id="sm-rotate-grace"
                       name="grace_period_minutes"
                       class="sm-input"
                       min="0"
                       value="{{ (int) ($defaultGraceMinutes ?? 60) }}">
                <p class="sm-help">Set to <code>0</code> to expire the old token immediately.</p>
            </div>

            <div class="sm-field" style="margin-bottom:0;">
                <label for="sm-rotate-name" class="sm-label">New Token Name (Optional)</label>
                <input type="text"
                       id="sm-rotate-name"
                       name="name"
                       class="sm-input"
                       placeholder="Leave blank to keep existing name">
            </div>
        </div>

        <div class="sm-dialog-footer">
            <button type="button" class="sm-btn" data-dialog-close>Cancel</button>
            <button type="submit" class="sm-btn sm-btn-primary">Rotate Token</button>
        </div>
    </form>
</dialog>

{{-- 3. Revoke Single Token Dialog --}}
<dialog id="sm-revoke-dialog" class="sm-dialog sm-dialog-sm" aria-labelledby="sm-revoke-dialog-title">
    <form method="POST" action="" class="sm-dialog-form">
        @csrf
        @method('DELETE')
        <div class="sm-dialog-header">
            <h2 class="sm-dialog-title" id="sm-revoke-dialog-title">Revoke Token</h2>
            <button type="button" class="sm-icon-btn" data-dialog-close aria-label="Close modal">&times;</button>
        </div>

        <div class="sm-dialog-body">
            <p style="margin:0 0 14px;color:var(--text-secondary);">
                Are you sure you want to revoke <strong data-bind-token-name>this token</strong> (<span class="mono" data-bind-token-id></span>)? Any clients using it will immediately lose access.
            </p>

            <div class="sm-field" style="margin-bottom:0;">
                <label for="sm-revoke-reason" class="sm-label">Revocation Reason (Optional)</label>
                <input type="text"
                       id="sm-revoke-reason"
                       name="reason"
                       class="sm-input"
                       placeholder="e.g. Compromised credentials, offboarding">
            </div>
        </div>

        <div class="sm-dialog-footer">
            <button type="button" class="sm-btn" data-dialog-close>Cancel</button>
            <button type="submit" class="sm-btn sm-btn-danger">Revoke Token</button>
        </div>
    </form>
</dialog>

{{-- 4. Revoke All User Tokens Dialog --}}
<dialog id="sm-revoke-user-dialog" class="sm-dialog sm-dialog-sm" aria-labelledby="sm-revoke-user-dialog-title">
    <form method="POST" action="{{ route('sanctum-manager.dashboard.tokens.revoke-user') }}" class="sm-dialog-form">
        @csrf
        <div class="sm-dialog-header">
            <h2 class="sm-dialog-title" id="sm-revoke-user-dialog-title">Revoke All User Tokens</h2>
            <button type="button" class="sm-icon-btn" data-dialog-close aria-label="Close modal">&times;</button>
        </div>

        <div class="sm-dialog-body">
            <p style="margin:0 0 14px;color:var(--text-secondary);">
                Immediately revoke every active personal access token belonging to a specific user.
            </p>

            <div class="sm-field">
                <label for="sm-revoke-user-input" class="sm-label">User (ID or Email) <span style="color:var(--danger-dot);">*</span></label>
                <input type="text"
                       id="sm-revoke-user-input"
                       name="user"
                       list="sm-users-list"
                       class="sm-input"
                       placeholder="user@example.com or 1"
                       required>
            </div>

            <div class="sm-field" style="margin-bottom:0;">
                <label for="sm-revoke-user-reason" class="sm-label">Reason (Optional)</label>
                <input type="text"
                       id="sm-revoke-user-reason"
                       name="reason"
                       class="sm-input"
                       placeholder="e.g. Security incident, account suspended">
            </div>
        </div>

        <div class="sm-dialog-footer">
            <button type="button" class="sm-btn" data-dialog-close>Cancel</button>
            <button type="submit" class="sm-btn sm-btn-danger">Revoke All Tokens</button>
        </div>
    </form>
</dialog>

{{-- 5. Prune Tokens Dialog --}}
<dialog id="sm-prune-dialog" class="sm-dialog sm-dialog-sm" aria-labelledby="sm-prune-dialog-title">
    <form method="POST" action="{{ route('sanctum-manager.dashboard.tokens.prune') }}" class="sm-dialog-form">
        @csrf
        <div class="sm-dialog-header">
            <h2 class="sm-dialog-title" id="sm-prune-dialog-title">Prune Expired &amp; Revoked Tokens</h2>
            <button type="button" class="sm-icon-btn" data-dialog-close aria-label="Close modal">&times;</button>
        </div>

        <div class="sm-dialog-body">
            <p style="margin:0 0 14px;color:var(--text-secondary);">
                Permanently remove stale expired or revoked tokens (and old audit logs when extended logging is enabled).
            </p>

            <div class="sm-field">
                <label for="sm-prune-days" class="sm-label">Retention Window for Tokens (Days)</label>
                <input type="number"
                       id="sm-prune-days"
                       name="days"
                       class="sm-input"
                       min="0"
                       value="30"
                       required>
                <p class="sm-help">Tokens expired or revoked more than this many days ago will be deleted.</p>
            </div>

            <div class="sm-field" style="margin-bottom:0;">
                <label for="sm-prune-log-days" class="sm-label">Retention Window for Audit Logs (Days)</label>
                <input type="number"
                       id="sm-prune-log-days"
                       name="log_days"
                       class="sm-input"
                       min="0"
                       value="90">
                <p class="sm-help">Applies only when the extended <code>sanctum_token_logs</code> table is installed.</p>
            </div>
        </div>

        <div class="sm-dialog-footer">
            <button type="button" class="sm-btn" data-dialog-close>Cancel</button>
            <button type="submit" class="sm-btn sm-btn-primary">Run Prune</button>
        </div>
    </form>
</dialog>
@endsection
