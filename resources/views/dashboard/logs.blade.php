@extends('sanctum-manager::dashboard.layout')

@section('title', __('sanctum-manager::sanctum-manager.logs.title'))

@section('content')
@php
    $logRequests = (bool) ($auditConfig['log_requests'] ?? false);
    $auditDriver = (string) ($auditConfig['driver'] ?? 'database');
    $updateInterval = (int) ($auditConfig['update_interval_seconds'] ?? 60);
@endphp

<div class="sm-page-head">
    <div>
        <h1 class="sm-page-title">{{ __('sanctum-manager::sanctum-manager.logs.title') }}</h1>
        <p class="sm-page-subtitle">{{ __('sanctum-manager::sanctum-manager.logs.subtitle') }}</p>
    </div>
    <div class="sm-actions">
        <span class="sm-badge {{ $logRequests ? 'sm-badge-success' : 'sm-badge-neutral' }}">
            <span class="sm-badge-dot" aria-hidden="true"></span>
            {{ $logRequests ? __('sanctum-manager::sanctum-manager.common.enabled') : __('sanctum-manager::sanctum-manager.common.disabled') }}
        </span>
        <span class="sm-badge sm-badge-info">
            <span class="mono">{{ $auditDriver }}</span>
        </span>
        <span class="sm-badge sm-badge-neutral">
            <span class="mono">{{ $updateInterval }}s</span>
        </span>
    </div>
</div>

@if (! $hasLogsTable)
    <section class="sm-card" aria-label="{{ __('sanctum-manager::sanctum-manager.logs.not_migrated_title') }}">
        <div class="sm-empty">
            <div class="sm-empty-icon" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <ellipse cx="12" cy="5" rx="9" ry="3"/>
                    <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/>
                    <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>
                </svg>
            </div>
            <h3>{{ __('sanctum-manager::sanctum-manager.logs.not_migrated_title') }}</h3>
            <p>{{ __('sanctum-manager::sanctum-manager.logs.not_migrated_body') }}</p>
            <div style="max-width:600px;margin:16px auto 0;text-align:left;">
                <pre class="sm-pre" id="sm-migrate-cmd">php artisan sanctum-manager:install --with-migrations &amp;&amp; php artisan migrate</pre>
                <div style="margin-top:10px;text-align:center;">
                    <button type="button" class="sm-btn sm-btn-sm" data-copy-target="sm-migrate-cmd">{{ __('sanctum-manager::sanctum-manager.common.copy') }}</button>
                </div>
            </div>
        </div>
    </section>
@else
    <section class="sm-card" aria-label="{{ __('sanctum-manager::sanctum-manager.logs.title') }}">
        <div class="sm-card-header">
            <div>
                <h2 class="sm-card-title">{{ __('sanctum-manager::sanctum-manager.logs.title') }}</h2>
            </div>
            @if ($logs)
                <span class="sm-badge sm-badge-neutral">{{ number_format($logs->total()) }}</span>
            @endif
        </div>

        @if (! $logs || $logs->count() === 0)
            <div class="sm-empty">
                <div class="sm-empty-icon" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                    </svg>
                </div>
                <h3>{{ __('sanctum-manager::sanctum-manager.logs.empty') }}</h3>
            </div>
        @else
            <div class="sm-table-wrap">
                <table class="sm-table">
                    <thead>
                        <tr>
                            <th>{{ __('sanctum-manager::sanctum-manager.logs.col_id') }}</th>
                            <th>{{ __('sanctum-manager::sanctum-manager.logs.col_token') }}</th>
                            <th>{{ __('sanctum-manager::sanctum-manager.logs.col_method') }}</th>
                            <th>{{ __('sanctum-manager::sanctum-manager.logs.col_path') }}</th>
                            <th>{{ __('sanctum-manager::sanctum-manager.logs.col_status') }}</th>
                            <th>{{ __('sanctum-manager::sanctum-manager.logs.col_ip') }}</th>
                            <th>{{ __('sanctum-manager::sanctum-manager.logs.col_user_agent') }}</th>
                            <th>{{ __('sanctum-manager::sanctum-manager.logs.col_time') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            @php
                                $statusCode = $log->status_code;
                                $statusClass = 'sm-badge-neutral';
                                if ($statusCode !== null) {
                                    if ($statusCode >= 200 && $statusCode < 300) {
                                        $statusClass = 'sm-badge-success';
                                    } elseif ($statusCode >= 300 && $statusCode < 400) {
                                        $statusClass = 'sm-badge-info';
                                    } elseif ($statusCode >= 400 && $statusCode < 500) {
                                        $statusClass = 'sm-badge-warning';
                                    } elseif ($statusCode >= 500) {
                                        $statusClass = 'sm-badge-danger';
                                    }
                                }
                                $methodUpper = strtoupper((string) $log->method);
                                $methodClass = match ($methodUpper) {
                                    'GET', 'HEAD' => 'sm-badge-info',
                                    'POST' => 'sm-badge-success',
                                    'PUT', 'PATCH' => 'sm-badge-warning',
                                    'DELETE' => 'sm-badge-danger',
                                    default => 'sm-badge-neutral',
                                };
                            @endphp
                            <tr>
                                <td class="mono" style="white-space:nowrap;">#{{ $log->id }}</td>
                                <td>
                                    @if ($log->token)
                                        <div class="sm-cell-title">{{ $log->token->name }}</div>
                                        <div class="sm-cell-sub mono">#{{ $log->personal_access_token_id }}</div>
                                    @else
                                        <span class="mono">#{{ $log->personal_access_token_id }}</span>
                                    @endif
                                </td>
                                <td style="white-space:nowrap;">
                                    <span class="sm-badge {{ $methodClass }} mono">{{ $methodUpper }}</span>
                                </td>
                                <td class="mono" style="max-width:280px;word-break:break-all;">{{ $log->path }}</td>
                                <td style="white-space:nowrap;">
                                    @if ($statusCode !== null)
                                        <span class="sm-badge {{ $statusClass }} mono">{{ $statusCode }}</span>
                                    @else
                                        <span class="sm-cell-sub">—</span>
                                    @endif
                                </td>
                                <td class="mono" style="white-space:nowrap;">{{ $log->ip_address ?: '—' }}</td>
                                <td style="max-width:240px;">
                                    @if ($log->user_agent)
                                        <span class="sm-cell-sub" title="{{ $log->user_agent }}">{{ \Illuminate\Support\Str::limit($log->user_agent, 56) }}</span>
                                    @else
                                        <span class="sm-cell-sub">—</span>
                                    @endif
                                </td>
                                <td class="mono" style="white-space:nowrap;">
                                    @if ($log->created_at)
                                        <div title="{{ $log->created_at->toIso8601String() }}">{{ $log->created_at->format('Y-m-d H:i:s') }}</div>
                                        <div class="sm-cell-sub">{{ $log->created_at->diffForHumans() }}</div>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($logs->hasPages())
                <div class="sm-pagination" role="navigation" aria-label="{{ __('sanctum-manager::sanctum-manager.logs.title') }}">
                    <div>
                        {{ __('sanctum-manager::sanctum-manager.common.showing', ['from' => $logs->firstItem(), 'to' => $logs->lastItem(), 'total' => $logs->total()]) }}
                    </div>
                    <div class="sm-pagination-links">
                        <a href="{{ $logs->previousPageUrl() ?: '#' }}"
                           class="sm-page-link {{ $logs->onFirstPage() ? 'is-disabled' : '' }}"
                           @if($logs->onFirstPage()) aria-disabled="true" tabindex="-1" @endif>
                            {{ __('sanctum-manager::sanctum-manager.common.previous') }}
                        </a>
                        @php
                            $startPage = max(1, $logs->currentPage() - 2);
                            $endPage = min($logs->lastPage(), $logs->currentPage() + 2);
                        @endphp
                        @for ($page = $startPage; $page <= $endPage; $page++)
                            <a href="{{ $logs->url($page) }}"
                               class="sm-page-link {{ $page === $logs->currentPage() ? 'is-active' : '' }}"
                               @if($page === $logs->currentPage()) aria-current="page" @endif>
                                {{ $page }}
                            </a>
                        @endfor
                        <a href="{{ $logs->nextPageUrl() ?: '#' }}"
                           class="sm-page-link {{ ! $logs->hasMorePages() ? 'is-disabled' : '' }}"
                           @if(! $logs->hasMorePages()) aria-disabled="true" tabindex="-1" @endif>
                            {{ __('sanctum-manager::sanctum-manager.common.next') }}
                        </a>
                    </div>
                </div>
            @endif
        @endif
    </section>
@endif
@endsection
