@extends('sanctum-manager::dashboard.layout')

@section('title', 'Scopes & Groups')

@section('content')
<div class="sm-page-head">
    <div>
        <h1 class="sm-page-title">Scopes &amp; Groups</h1>
        <p class="sm-page-subtitle">Inspect registered API scopes, hierarchy implications, and reusable scope groups.</p>
    </div>
    <div class="sm-actions">
        <span class="sm-badge {{ ! empty($strictValidation) ? 'sm-badge-success' : 'sm-badge-warning' }}">
            <span class="sm-badge-dot" aria-hidden="true"></span>
            Strict Validation: {{ ! empty($strictValidation) ? 'Enabled' : 'Disabled' }}
        </span>
    </div>
</div>

<section style="margin-bottom: 24px;" aria-labelledby="sm-groups-heading">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;gap:12px;">
        <h2 id="sm-groups-heading" style="margin:0;font-size:16px;font-weight:700;">
            Scope Groups <span style="color:var(--text-muted);font-weight:500;">({{ count($groups) }})</span>
        </h2>
    </div>

    @if (count($groups) === 0)
        <div class="sm-card">
            <div class="sm-empty">
                <h3>No scope groups registered</h3>
                <p>Define reusable groups in <code>config/sanctum-manager.php</code> or via <code>SanctumManager::defineScopeGroup()</code> to issue bundles with <code>&#64;groupName</code>.</p>
            </div>
        </div>
    @else
        <div class="sm-grid-3">
            @foreach ($groups as $group)
                <article class="sm-card" style="margin-bottom:0;">
                    <div class="sm-card-header">
                        <div>
                            <h3 class="sm-card-title mono">{{ '@' . $group->name }}</h3>
                            @if ($group->description !== '')
                                <p class="sm-card-subtitle">{{ $group->description }}</p>
                            @endif
                        </div>
                        <span class="sm-badge sm-badge-accent">{{ count($group->scopes) }} {{ count($group->scopes) === 1 ? 'scope' : 'scopes' }}</span>
                    </div>
                    <div class="sm-card-body">
                        @if (count($group->scopes) > 0)
                            <div class="sm-chips">
                                @foreach ($group->scopes as $groupScope)
                                    <span class="sm-chip">{{ $groupScope }}</span>
                                @endforeach
                            </div>
                        @else
                            <span class="sm-cell-sub">No scopes in this group.</span>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</section>

<section class="sm-card" aria-labelledby="sm-scopes-heading">
    <div class="sm-card-header">
        <div>
            <h2 class="sm-card-title" id="sm-scopes-heading">Registered Scopes ({{ count($scopes) }})</h2>
            <p class="sm-card-subtitle">All scopes defined in configuration or programmatically via the scope registry.</p>
        </div>
    </div>

    @if (count($scopes) === 0)
        <div class="sm-empty">
            <h3>No scopes registered</h3>
            <p>Add scope definitions in <code>config/sanctum-manager.php</code> under <code>scopes.definitions</code> or register them at boot time.</p>
        </div>
    @else
        <div class="sm-table-wrap">
            <table class="sm-table">
                <thead>
                    <tr>
                        <th>Scope</th>
                        <th>Group</th>
                        <th>Description</th>
                        <th>Implies</th>
                        <th>Flags</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($scopes as $scope)
                        <tr>
                            <td>
                                <span class="sm-chip sm-chip-accent">{{ $scope->name }}</span>
                            </td>
                            <td>
                                @if ($scope->group !== null && $scope->group !== '')
                                    <span class="sm-chip">{{ $scope->group }}</span>
                                @else
                                    <span class="sm-cell-sub">—</span>
                                @endif
                            </td>
                            <td>
                                @if ($scope->description !== '')
                                    {{ $scope->description }}
                                @else
                                    <span class="sm-cell-sub">No description</span>
                                @endif
                            </td>
                            <td>
                                @if (count($scope->implies) > 0)
                                    <div class="sm-chips">
                                        @foreach ($scope->implies as $implied)
                                            <span class="sm-chip">{{ $implied }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="sm-cell-sub">—</span>
                                @endif
                            </td>
                            <td style="white-space:nowrap;">
                                <div class="sm-chips">
                                    @if ($scope->isDefault)
                                        <span class="sm-badge sm-badge-success">Default</span>
                                    @endif
                                    @if ($scope->isDeprecated)
                                        <span class="sm-badge sm-badge-warning">Deprecated</span>
                                    @endif
                                    @if (! $scope->isDefault && ! $scope->isDeprecated)
                                        <span class="sm-cell-sub">—</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection
