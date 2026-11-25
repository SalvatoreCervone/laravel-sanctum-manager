@extends('sanctum-manager::dashboard.layout')

@section('title', __('sanctum-manager::sanctum-manager.tokens.title'))

@section('content')
@php
    $currentStatus = $filters['status'] ?? 'all';
    $currentSearch = $filters['search'] ?? '';
    $statusTabs = [
        'all' => ['label' => __('sanctum-manager::sanctum-manager.tokens.filter_all'), 'count' => $stats['total'] ?? 0],
        'active' => ['label' => __('sanctum-manager::sanctum-manager.tokens.filter_active'), 'count' => $stats['active'] ?? 0],
        'grace' => ['label' => __('sanctum-manager::sanctum-manager.tokens.filter_grace'), 'count' => $stats['grace'] ?? 0],
        'expired' => ['label' => __('sanctum-manager::sanctum-manager.tokens.filter_expired'), 'count' => $stats['expired'] ?? 0],
        'revoked' => ['label' => __('sanctum-manager::sanctum-manager.tokens.filter_revoked'), 'count' => $stats['revoked'] ?? 0],
    ];
@endphp

<div class="sm-page-head">
    <div>
        <h1 class="sm-page-title">{{ __('sanctum-manager::sanctum-manager.tokens.title') }}</h1>
        <p class="sm-page-subtitle">{{ __('sanctum-manager::sanctum-manager.tokens.subtitle') }}</p>
    </div>
    <div class="sm-actions">
        <button type="button" class="sm-btn sm-btn-primary" data-dialog-open="sm-issue-dialog">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="12" y1="5" x2="12" y2="19"/>
                <line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            {{ __('sanctum-manager::sanctum-manager.tokens.issue_button') }}
        </button>
        <button type="button" class="sm-btn sm-btn-danger-outline" data-dialog-open="sm-revoke-user-dialog">
            {{ __('sanctum-manager::sanctum-manager.tokens.revoke_user_button') }}
        </button>
        <button type="button" class="sm-btn" data-dialog-open="sm-prune-dialog">
            {{ __('sanctum-manager::sanctum-manager.tokens.prune_button') }}
        </button>
    </div>
</div>

<section class="sm-stats" aria-label="{{ __('sanctum-manager::sanctum-manager.tokens.title') }}">
    <div class="sm-stat-card">
        <div class="sm-stat-label">
            <span>{{ __('sanctum-manager::sanctum-manager.tokens.kpi_total') }}</span>
        </div>
        <div class="sm-stat-value">{{ number_format((int) ($stats['total'] ?? 0)) }}</div>
    </div>
    <div class="sm-stat-card">
        <div class="sm-stat-label">
            <span>{{ __('sanctum-manager::sanctum-manager.tokens.kpi_active') }}</span>
            <span class="sm-badge-dot" style="background: var(--success-dot);" aria-hidden="true"></span>
        </div>
        <div class="sm-stat-value">{{ number_format((int) ($stats['active'] ?? 0)) }}</div>
    </div>
    <div class="sm-stat-card">
        <div class="sm-stat-label">
            <span>{{ __('sanctum-manager::sanctum-manager.tokens.kpi_grace') }}</span>
            <span class="sm-badge-dot" style="background: var(--warning-dot);" aria-hidden="true"></span>
        </div>
        <div class="sm-stat-value">{{ number_format((int) ($stats['grace'] ?? 0)) }}</div>
    </div>
    <div class="sm-stat-card">
        <div class="sm-stat-label">
            <span>{{ __('sanctum-manager::sanctum-manager.tokens.kpi_expired') }}</span>
            <span class="sm-badge-dot" style="background: var(--neutral-dot);" aria-hidden="true"></span>
        </div>
        <div class="sm-stat-value">{{ number_format((int) ($stats['expired'] ?? 0)) }}</div>
    </div>
    <div class="sm-stat-card">
        <div class="sm-stat-label">
            <span>{{ __('sanctum-manager::sanctum-manager.tokens.kpi_revoked') }}</span>
            <span class="sm-badge-dot" style="background: var(--danger-dot);" aria-hidden="true"></span>
        </div>
        <div class="sm-stat-value">{{ number_format((int) ($stats['revoked'] ?? 0)) }}</div>
    </div>
    <div class="sm-stat-card">
        <div class="sm-stat-label">
            <span>{{ __('sanctum-manager::sanctum-manager.tokens.kpi_scopes') }}</span>
        </div>
        <div class="sm-stat-value">
            {{ number_format((int) ($stats['scopes'] ?? 0)) }}
            <span style="font-size:15px;font-weight:500;color:var(--text-muted);">/ {{ number_format((int) ($stats['groups'] ?? 0)) }}</span>
        </div>
    </div>
</section>

<section class="sm-card" aria-label="{{ __('sanctum-manager::sanctum-manager.tokens.title') }}">
    <div class="sm-toolbar">
        <div class="sm-tabs" role="tablist" aria-label="{{ __('sanctum-manager::sanctum-manager.common.filter') }}">
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
            <label for="sm-token-search" class="sr-only">{{ __('sanctum-manager::sanctum-manager.common.search') }}</label>
            <input type="search"
                   id="sm-token-search"
                   name="search"
                   class="sm-input"
                   value="{{ $currentSearch }}"
                   placeholder="{{ __('sanctum-manager::sanctum-manager.tokens.search_placeholder') }}">
            <button type="submit" class="sm-btn">{{ __('sanctum-manager::sanctum-manager.common.search') }}</button>
            @if ($currentSearch !== '')
                <a href="{{ route('sanctum-manager.dashboard.index', $currentStatus !== 'all' ? ['status' => $currentStatus] : []) }}" class="sm-btn">
                    {{ __('sanctum-manager::sanctum-manager.common.reset') }}
                </a>
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
            <h3>{{ __('sanctum-manager::sanctum-manager.tokens.empty') }}</h3>
        </div>
    @else
        <div class="sm-table-wrap">
            <table class="sm-table">
                <thead>
                    <tr>
                        <th>{{ __('sanctum-manager::sanctum-manager.tokens.col_token') }}</th>
                        <th>{{ __('sanctum-manager::sanctum-manager.tokens.col_owner') }}</th>
                        <th>{{ __('sanctum-manager::sanctum-manager.tokens.col_status') }}</th>
                        <th>{{ __('sanctum-manager::sanctum-manager.tokens.col_scopes') }}</th>
                        <th>{{ __('sanctum-manager::sanctum-manager.tokens.col_constraints') }}</th>
                        <th>{{ __('sanctum-manager::sanctum-manager.tokens.col_timestamps') }}</th>
                        <th style="text-align:right;">{{ __('sanctum-manager::sanctum-manager.common.actions') }}</th>
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
                                    <div class="sm-cell-title">{{ $ownerName ?: '#'.$token->tokenable_id }}</div>
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
                                        {{ __('sanctum-manager::sanctum-manager.badges.revoked') }}
                                    </span>
                                    @if ($revocationReason)
                                        <div class="sm-cell-sub" title="{{ $revocationReason }}">{{ \Illuminate\Support\Str::limit($revocationReason, 36) }}</div>
                                    @endif
                                @elseif ($isExpired)
                                    <span class="sm-badge sm-badge-neutral">
                                        <span class="sm-badge-dot" aria-hidden="true"></span>
                                        {{ __('sanctum-manager::sanctum-manager.badges.expired') }}
                                    </span>
                                @elseif ($isInGrace)
                                    <span class="sm-badge sm-badge-warning">
                                        <span class="sm-badge-dot" aria-hidden="true"></span>
                                        {{ __('sanctum-manager::sanctum-manager.badges.grace') }}
                                    </span>
                                    @if ($graceExpiresAt)
                                        <div class="sm-cell-sub mono">{{ __('sanctum-manager::sanctum-manager.tokens.grace_until') }} {{ $graceExpiresAt->diffForHumans() }}</div>
                                    @endif
                                @else
                                    <span class="sm-badge sm-badge-success">
                                        <span class="sm-badge-dot" aria-hidden="true"></span>
                                        {{ __('sanctum-manager::sanctum-manager.badges.active') }}
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
                                    <span class="sm-cell-sub">{{ __('sanctum-manager::sanctum-manager.common.none') }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="sm-chips" style="flex-direction:column;align-items:flex-start;gap:4px;">
                                    <span class="sm-cell-sub">
                                        {{ __('sanctum-manager::sanctum-manager.tokens.uses') }}:
                                        <strong class="mono">{{ number_format($usesCount) }}@if($maxUses !== null) / {{ number_format($maxUses) }}@endif</strong>
                                    </span>
                                    @if (count($allowedIps) > 0)
                                        <span class="sm-chip" title="{{ implode(', ', $allowedIps) }}">
                                            {{ __('sanctum-manager::sanctum-manager.tokens.ips') }}: {{ implode(', ', array_slice($allowedIps, 0, 2)) }}@if(count($allowedIps) > 2) +{{ count($allowedIps) - 2 }}@endif
                                        </span>
                                    @endif
                                    @if (count($allowedOrigins) > 0)
                                        <span class="sm-chip" title="{{ implode(', ', $allowedOrigins) }}">
                                            {{ __('sanctum-manager::sanctum-manager.tokens.origins') }}: {{ implode(', ', array_slice($allowedOrigins, 0, 1)) }}@if(count($allowedOrigins) > 1) +{{ count($allowedOrigins) - 1 }}@endif
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td style="white-space:nowrap;">
                                <div class="sm-cell-sub">
                                    {{ __('sanctum-manager::sanctum-manager.tokens.created') }}:
                                    <span class="mono" title="{{ $token->created_at?->toIso8601String() }}">{{ $token->created_at?->diffForHumans() ?? '—' }}</span>
                                </div>
                                <div class="sm-cell-sub">
                                    {{ __('sanctum-manager::sanctum-manager.tokens.last_used') }}:
                                    <span class="mono" title="{{ $token->last_used_at?->toIso8601String() }}">{{ $token->last_used_at?->diffForHumans() ?? __('sanctum-manager::sanctum-manager.common.never') }}</span>
                                </div>
                                <div class="sm-cell-sub">
                                    {{ __('sanctum-manager::sanctum-manager.tokens.expires') }}:
                                    <span class="mono" title="{{ $token->expires_at?->toIso8601String() }}">{{ $token->expires_at ? $token->expires_at->format('Y-m-d H:i') : __('sanctum-manager::sanctum-manager.common.never') }}</span>
                                </div>
                            </td>
                            <td style="text-align:right;white-space:nowrap;">
                                <div style="display:inline-flex;gap:6px;align-items:center;">
                                    <button type="button"
                                            class="sm-btn sm-btn-sm"
                                            data-dialog-open="sm-edit-scopes-dialog"
                                            data-form-action="{{ route('sanctum-manager.dashboard.tokens.update-scopes', ['tokenId' => $token->getKey()]) }}"
                                            data-token-name="{{ $token->name }}"
                                            data-token-id="{{ $token->getKey() }}"
                                            data-token-scopes='@json($effectiveScopes)'
                                            @if(! $canRotate) disabled @endif>
                                        {{ __('sanctum-manager::sanctum-manager.tokens.edit_scopes') }}
                                    </button>
                                    <button type="button"
                                            class="sm-btn sm-btn-sm"
                                            data-dialog-open="sm-rotate-dialog"
                                            data-form-action="{{ route('sanctum-manager.dashboard.tokens.rotate', ['tokenId' => $token->getKey()]) }}"
                                            data-token-name="{{ $token->name }}"
                                            data-token-id="{{ $token->getKey() }}"
                                            @if(! $canRotate) disabled @endif>
                                        {{ __('sanctum-manager::sanctum-manager.tokens.rotate') }}
                                    </button>
                                    <button type="button"
                                            class="sm-btn sm-btn-sm sm-btn-danger-outline"
                                            data-dialog-open="sm-revoke-dialog"
                                            data-form-action="{{ route('sanctum-manager.dashboard.tokens.revoke', ['tokenId' => $token->getKey()]) }}"
                                            data-token-name="{{ $token->name }}"
                                            data-token-id="{{ $token->getKey() }}">
                                        {{ __('sanctum-manager::sanctum-manager.tokens.revoke') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($tokens->hasPages())
            <div class="sm-pagination" role="navigation" aria-label="{{ __('sanctum-manager::sanctum-manager.tokens.title') }}">
                <div>
                    {{ __('sanctum-manager::sanctum-manager.common.showing', ['from' => $tokens->firstItem(), 'to' => $tokens->lastItem(), 'total' => $tokens->total()]) }}
                </div>
                <div class="sm-pagination-links">
                    <a href="{{ $tokens->previousPageUrl() ?: '#' }}"
                       class="sm-page-link {{ $tokens->onFirstPage() ? 'is-disabled' : '' }}"
                       @if($tokens->onFirstPage()) aria-disabled="true" tabindex="-1" @endif>
                        {{ __('sanctum-manager::sanctum-manager.common.previous') }}
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
                        {{ __('sanctum-manager::sanctum-manager.common.next') }}
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
            <h2 class="sm-dialog-title" id="sm-issue-dialog-title">{{ __('sanctum-manager::sanctum-manager.tokens.modal_issue_title') }}</h2>
            <button type="button" class="sm-icon-btn" data-dialog-close aria-label="{{ __('sanctum-manager::sanctum-manager.common.close') }}">&times;</button>
        </div>

        <div class="sm-dialog-body">
            <div class="sm-field-grid">
                <div class="sm-field">
                    <label for="sm-issue-user" class="sm-label">{{ __('sanctum-manager::sanctum-manager.tokens.field_user') }} <span style="color:var(--danger-dot);">*</span></label>
                    <input type="text"
                           id="sm-issue-user"
                           name="user"
                           list="sm-users-list"
                           class="sm-input"
                           value="{{ old('user') }}"
                           placeholder="user@example.com / 1"
                           required>
                </div>

                <div class="sm-field">
                    <label for="sm-issue-name" class="sm-label">{{ __('sanctum-manager::sanctum-manager.tokens.field_token_name') }} <span style="color:var(--danger-dot);">*</span></label>
                    <input type="text"
                           id="sm-issue-name"
                           name="name"
                           class="sm-input"
                           value="{{ old('name') }}"
                           placeholder="CI Deploy Key"
                           required>
                </div>
            </div>

            @if (count($groups) > 0 || count($scopes) > 0)
                <div class="sm-field">
                    <span class="sm-label">{{ __('sanctum-manager::sanctum-manager.tokens.field_scopes') }}</span>
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
                        @foreach ($scopes as $scope)
                            <label class="sm-check-item">
                                <input type="checkbox" name="scopes[]" value="{{ $scope->name }}" @if($scope->isDefault) checked @endif>
                                <span>
                                    <strong class="mono">{{ $scope->name }}</strong>
                                    @if ($scope->isDeprecated)
                                        <span class="sm-badge sm-badge-warning" style="padding:1px 5px;font-size:10px;">{{ __('sanctum-manager::sanctum-manager.badges.deprecated') }}</span>
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
                <label for="sm-issue-custom-scopes" class="sm-label">{{ __('sanctum-manager::sanctum-manager.tokens.field_custom_scopes') }}</label>
                <input type="text"
                       id="sm-issue-custom-scopes"
                       name="custom_scopes"
                       class="sm-input mono"
                       value="{{ old('custom_scopes') }}"
                       placeholder="orders:read, billing:export">
            </div>

            <div class="sm-field-grid">
                <div class="sm-field">
                    <label for="sm-issue-expires" class="sm-label">{{ __('sanctum-manager::sanctum-manager.tokens.field_expires_days') }}</label>
                    <input type="number"
                           id="sm-issue-expires"
                           name="expires_in_days"
                           class="sm-input"
                           min="1"
                           value="{{ old('expires_in_days') }}"
                           placeholder="{{ __('sanctum-manager::sanctum-manager.common.never') }}">
                </div>

                <div class="sm-field">
                    <label for="sm-issue-max-uses" class="sm-label">{{ __('sanctum-manager::sanctum-manager.tokens.field_max_uses') }}</label>
                    <input type="number"
                           id="sm-issue-max-uses"
                           name="max_uses"
                           class="sm-input"
                           min="1"
                           value="{{ old('max_uses') }}"
                           placeholder="∞">
                </div>

                <div class="sm-field">
                    <label for="sm-issue-prefix" class="sm-label">{{ __('sanctum-manager::sanctum-manager.tokens.field_prefix') }}</label>
                    <input type="text"
                           id="sm-issue-prefix"
                           name="token_prefix"
                           class="sm-input mono"
                           value="{{ old('token_prefix') }}"
                           placeholder="sk_live_">
                </div>

                <div class="sm-field">
                    <label for="sm-issue-rate-limit" class="sm-label">{{ __('sanctum-manager::sanctum-manager.tokens.field_rate_limit') }}</label>
                    <input type="number"
                           id="sm-issue-rate-limit"
                           name="rate_limit_per_minute"
                           class="sm-input"
                           min="1"
                           value="{{ old('rate_limit_per_minute') }}"
                           placeholder="60">
                </div>
            </div>

            <div class="sm-field-grid">
                <div class="sm-field" style="margin-bottom:0;">
                    <label for="sm-issue-ips" class="sm-label">{{ __('sanctum-manager::sanctum-manager.tokens.field_allowed_ips') }}</label>
                    <input type="text"
                           id="sm-issue-ips"
                           name="allowed_ips"
                           class="sm-input mono"
                           value="{{ old('allowed_ips') }}"
                           placeholder="10.0.0.1, 192.168.1.0/24">
                </div>

                <div class="sm-field" style="margin-bottom:0;">
                    <label for="sm-issue-origins" class="sm-label">{{ __('sanctum-manager::sanctum-manager.tokens.field_allowed_origins') }}</label>
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
            <button type="button" class="sm-btn" data-dialog-close>{{ __('sanctum-manager::sanctum-manager.common.cancel') }}</button>
            <button type="submit" class="sm-btn sm-btn-primary">{{ __('sanctum-manager::sanctum-manager.tokens.submit_issue') }}</button>
        </div>
    </form>
</dialog>

{{-- 2. Rotate Token Dialog --}}
<dialog id="sm-rotate-dialog" class="sm-dialog sm-dialog-sm" aria-labelledby="sm-rotate-dialog-title">
    <form method="POST" action="" class="sm-dialog-form">
        @csrf
        <div class="sm-dialog-header">
            <h2 class="sm-dialog-title" id="sm-rotate-dialog-title">{{ __('sanctum-manager::sanctum-manager.tokens.modal_rotate_title') }}</h2>
            <button type="button" class="sm-icon-btn" data-dialog-close aria-label="{{ __('sanctum-manager::sanctum-manager.common.close') }}">&times;</button>
        </div>

        <div class="sm-dialog-body">
            <p style="margin:0 0 14px;color:var(--text-secondary);">
                <strong data-bind-token-name></strong> <span class="mono" data-bind-token-id></span>
            </p>

            <div class="sm-field">
                <label for="sm-rotate-grace" class="sm-label">{{ __('sanctum-manager::sanctum-manager.tokens.field_grace_minutes') }}</label>
                <input type="number"
                       id="sm-rotate-grace"
                       name="grace_period_minutes"
                       class="sm-input"
                       min="0"
                       value="{{ (int) ($defaultGraceMinutes ?? 60) }}">
            </div>

            <div class="sm-field" style="margin-bottom:0;">
                <label for="sm-rotate-name" class="sm-label">{{ __('sanctum-manager::sanctum-manager.tokens.field_new_name') }}</label>
                <input type="text"
                       id="sm-rotate-name"
                       name="name"
                       class="sm-input">
            </div>
        </div>

        <div class="sm-dialog-footer">
            <button type="button" class="sm-btn" data-dialog-close>{{ __('sanctum-manager::sanctum-manager.common.cancel') }}</button>
            <button type="submit" class="sm-btn sm-btn-primary">{{ __('sanctum-manager::sanctum-manager.tokens.submit_rotate') }}</button>
        </div>
    </form>
</dialog>

{{-- 3. Revoke Single Token Dialog --}}
<dialog id="sm-revoke-dialog" class="sm-dialog sm-dialog-sm" aria-labelledby="sm-revoke-dialog-title">
    <form method="POST" action="" class="sm-dialog-form">
        @csrf
        @method('DELETE')
        <div class="sm-dialog-header">
            <h2 class="sm-dialog-title" id="sm-revoke-dialog-title">{{ __('sanctum-manager::sanctum-manager.tokens.modal_revoke_title') }}</h2>
            <button type="button" class="sm-icon-btn" data-dialog-close aria-label="{{ __('sanctum-manager::sanctum-manager.common.close') }}">&times;</button>
        </div>

        <div class="sm-dialog-body">
            <p style="margin:0 0 14px;color:var(--text-secondary);">
                <strong data-bind-token-name></strong> <span class="mono" data-bind-token-id></span>
            </p>

            <div class="sm-field" style="margin-bottom:0;">
                <label for="sm-revoke-reason" class="sm-label">{{ __('sanctum-manager::sanctum-manager.tokens.field_reason') }}</label>
                <input type="text"
                       id="sm-revoke-reason"
                       name="reason"
                       class="sm-input">
            </div>
        </div>

        <div class="sm-dialog-footer">
            <button type="button" class="sm-btn" data-dialog-close>{{ __('sanctum-manager::sanctum-manager.common.cancel') }}</button>
            <button type="submit" class="sm-btn sm-btn-danger">{{ __('sanctum-manager::sanctum-manager.tokens.submit_revoke') }}</button>
        </div>
    </form>
</dialog>

{{-- 4. Revoke All User Tokens Dialog --}}
<dialog id="sm-revoke-user-dialog" class="sm-dialog sm-dialog-sm" aria-labelledby="sm-revoke-user-dialog-title">
    <form method="POST" action="{{ route('sanctum-manager.dashboard.tokens.revoke-user') }}" class="sm-dialog-form">
        @csrf
        <div class="sm-dialog-header">
            <h2 class="sm-dialog-title" id="sm-revoke-user-dialog-title">{{ __('sanctum-manager::sanctum-manager.tokens.modal_revoke_user_title') }}</h2>
            <button type="button" class="sm-icon-btn" data-dialog-close aria-label="{{ __('sanctum-manager::sanctum-manager.common.close') }}">&times;</button>
        </div>

        <div class="sm-dialog-body">
            <div class="sm-field">
                <label for="sm-revoke-user-input" class="sm-label">{{ __('sanctum-manager::sanctum-manager.tokens.field_user') }} <span style="color:var(--danger-dot);">*</span></label>
                <input type="text"
                       id="sm-revoke-user-input"
                       name="user"
                       list="sm-users-list"
                       class="sm-input"
                       placeholder="user@example.com / 1"
                       required>
            </div>

            <div class="sm-field" style="margin-bottom:0;">
                <label for="sm-revoke-user-reason" class="sm-label">{{ __('sanctum-manager::sanctum-manager.tokens.field_reason') }}</label>
                <input type="text"
                       id="sm-revoke-user-reason"
                       name="reason"
                       class="sm-input">
            </div>
        </div>

        <div class="sm-dialog-footer">
            <button type="button" class="sm-btn" data-dialog-close>{{ __('sanctum-manager::sanctum-manager.common.cancel') }}</button>
            <button type="submit" class="sm-btn sm-btn-danger">{{ __('sanctum-manager::sanctum-manager.tokens.submit_revoke_user') }}</button>
        </div>
    </form>
</dialog>

{{-- 5. Prune Tokens Dialog --}}
<dialog id="sm-prune-dialog" class="sm-dialog sm-dialog-sm" aria-labelledby="sm-prune-dialog-title">
    <form method="POST" action="{{ route('sanctum-manager.dashboard.tokens.prune') }}" class="sm-dialog-form">
        @csrf
        <div class="sm-dialog-header">
            <h2 class="sm-dialog-title" id="sm-prune-dialog-title">{{ __('sanctum-manager::sanctum-manager.tokens.modal_prune_title') }}</h2>
            <button type="button" class="sm-icon-btn" data-dialog-close aria-label="{{ __('sanctum-manager::sanctum-manager.common.close') }}">&times;</button>
        </div>

        <div class="sm-dialog-body">
            <div class="sm-field">
                <label for="sm-prune-days" class="sm-label">{{ __('sanctum-manager::sanctum-manager.tokens.field_prune_days') }}</label>
                <input type="number"
                       id="sm-prune-days"
                       name="days"
                       class="sm-input"
                       min="0"
                       value="30"
                       required>
            </div>

            <div class="sm-field" style="margin-bottom:0;">
                <label for="sm-prune-log-days" class="sm-label">{{ __('sanctum-manager::sanctum-manager.tokens.field_prune_log_days') }}</label>
                <input type="number"
                       id="sm-prune-log-days"
                       name="log_days"
                       class="sm-input"
                       min="0"
                       value="90">
            </div>
        </div>

        <div class="sm-dialog-footer">
            <button type="button" class="sm-btn" data-dialog-close>{{ __('sanctum-manager::sanctum-manager.common.cancel') }}</button>
            <button type="submit" class="sm-btn sm-btn-primary">{{ __('sanctum-manager::sanctum-manager.tokens.submit_prune') }}</button>
        </div>
    </form>
</dialog>

{{-- 6. Edit Token Scopes Dialog --}}
<dialog id="sm-edit-scopes-dialog" class="sm-dialog" aria-labelledby="sm-edit-scopes-dialog-title">
    <form method="POST" action="#" class="sm-dialog-form" id="sm-edit-scopes-form">
        @csrf
        @method('PATCH')
        <div class="sm-dialog-header">
            <h2 class="sm-dialog-title" id="sm-edit-scopes-dialog-title">
                {{ __('sanctum-manager::sanctum-manager.tokens.modal_edit_scopes_title') }}:
                <span data-token-name-target class="mono"></span>
            </h2>
            <button type="button" class="sm-icon-btn" data-dialog-close aria-label="{{ __('sanctum-manager::sanctum-manager.common.close') }}">&times;</button>
        </div>

        <div class="sm-dialog-body">
            @if (count($groups) > 0 || count($scopes) > 0)
                <div class="sm-field">
                    <span class="sm-label">{{ __('sanctum-manager::sanctum-manager.tokens.field_scopes') }}</span>
                    <div class="sm-check-grid" id="sm-edit-scopes-grid">
                        @foreach ($groups as $group)
                            <label class="sm-check-item">
                                <input type="checkbox" name="scopes[]" value="{{ '@' . $group->name }}">
                                <span>
                                    <span class="sm-check-title mono">{{ '@' . $group->name }}</span>
                                    @if ($group->description !== '')
                                        <span class="sm-check-desc">{{ $group->description }}</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                        @foreach ($scopes as $scope)
                            <label class="sm-check-item">
                                <input type="checkbox" name="scopes[]" value="{{ $scope->name }}">
                                <span>
                                    <span class="sm-check-title mono">{{ $scope->name }}</span>
                                    @if ($scope->description !== '')
                                        <span class="sm-check-desc">{{ $scope->description }}</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="sm-field" style="margin-bottom:0;">
                <label for="sm-edit-custom-scopes" class="sm-label">{{ __('sanctum-manager::sanctum-manager.tokens.field_custom_scopes') }}</label>
                <input type="text"
                       id="sm-edit-custom-scopes"
                       name="custom_scopes"
                       class="sm-input mono"
                       placeholder="orders:read, billing:write">
            </div>
        </div>

        <div class="sm-dialog-footer">
            <button type="button" class="sm-btn" data-dialog-close>{{ __('sanctum-manager::sanctum-manager.common.cancel') }}</button>
            <button type="submit" class="sm-btn sm-btn-primary">{{ __('sanctum-manager::sanctum-manager.tokens.submit_update_scopes') }}</button>
        </div>
    </form>
</dialog>
@endsection

@push('scripts')
<script>
    (function () {
        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-dialog-open="sm-edit-scopes-dialog"]');
            if (!trigger) {
                return;
            }

            var scopes = [];
            try {
                scopes = JSON.parse(trigger.getAttribute('data-token-scopes') || '[]');
            } catch (e) {
                scopes = [];
            }

            var form = document.getElementById('sm-edit-scopes-form');
            if (!form) {
                return;
            }

            var knownValues = new Set();
            form.querySelectorAll('input[type="checkbox"][name="scopes[]"]').forEach(function (checkbox) {
                knownValues.add(checkbox.value);
                checkbox.checked = scopes.indexOf(checkbox.value) !== -1;
            });

            var customScopes = scopes.filter(function (scope) {
                return !knownValues.has(scope);
            });

            var customInput = document.getElementById('sm-edit-custom-scopes');
            if (customInput) {
                customInput.value = customScopes.join(', ');
            }
        });
    })();
</script>
@endpush
