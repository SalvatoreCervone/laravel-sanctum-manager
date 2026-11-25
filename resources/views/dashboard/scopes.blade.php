@extends('sanctum-manager::dashboard.layout')

@section('title', __('sanctum-manager::sanctum-manager.scopes.title'))

@section('content')
@php
    $storageDriver = ($scopeStorageDriver ?? 'file') === 'database' ? 'database' : 'file';
    $storagePath = (string) ($scopeStoragePath ?? '');
    $persistedScopes = is_array($persistedScopeNames ?? null) ? $persistedScopeNames : [];
    $persistedGroups = is_array($persistedGroupNames ?? null) ? $persistedGroupNames : [];
    $dynamicSourceLabel = $storageDriver === 'database'
        ? __('sanctum-manager::sanctum-manager.badges.source_database')
        : __('sanctum-manager::sanctum-manager.badges.source_file');
    $dynamicSourceBadge = $storageDriver === 'database' ? 'sm-badge-success' : 'sm-badge-info';

    $scopeCategories = [];
    foreach ($scopes as $scopeItem) {
        if ($scopeItem->group !== null && $scopeItem->group !== '') {
            $scopeCategories[$scopeItem->group] = $scopeItem->group;
        }
    }
@endphp

<div class="sm-page-head">
    <div>
        <h1 class="sm-page-title">{{ __('sanctum-manager::sanctum-manager.scopes.title') }}</h1>
        <p class="sm-page-subtitle">{{ __('sanctum-manager::sanctum-manager.scopes.subtitle') }}</p>
    </div>
    <div class="sm-actions">
        <span class="sm-badge {{ ! empty($strictValidation) ? 'sm-badge-success' : 'sm-badge-warning' }}">
            <span class="sm-badge-dot" aria-hidden="true"></span>
            {{ ! empty($strictValidation) ? __('sanctum-manager::sanctum-manager.badges.strict_on') : __('sanctum-manager::sanctum-manager.badges.strict_off') }}
        </span>

        @if ($storageDriver === 'database')
            <span class="sm-badge sm-badge-success">
                <span class="sm-badge-dot" aria-hidden="true"></span>
                {{ __('sanctum-manager::sanctum-manager.scopes.storage_db') }}
            </span>
        @else
            <span class="sm-badge sm-badge-warning" title="{{ $storagePath }}">
                <span class="sm-badge-dot" aria-hidden="true"></span>
                {{ __('sanctum-manager::sanctum-manager.scopes.storage_file') }}
            </span>
        @endif

        <button type="button" class="sm-btn sm-btn-primary" data-create-scope>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="12" y1="5" x2="12" y2="19"/>
                <line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            {{ __('sanctum-manager::sanctum-manager.scopes.new_scope') }}
        </button>

        <button type="button" class="sm-btn" data-create-group>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="12" y1="5" x2="12" y2="19"/>
                <line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            {{ __('sanctum-manager::sanctum-manager.scopes.new_group') }}
        </button>
    </div>
</div>

@if ($storageDriver === 'file')
    <div class="sm-alert sm-alert-warning" role="note">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px;" aria-hidden="true">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="16" x2="12" y2="12"/>
            <line x1="12" y1="8" x2="12.01" y2="8"/>
        </svg>
        <div>
            {{ __('sanctum-manager::sanctum-manager.scopes.storage_file_hint', ['path' => $storagePath]) }}
        </div>
    </div>
@endif

{{-- Scope Groups Section --}}
<section style="margin-bottom: 24px;" aria-labelledby="sm-groups-heading">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;gap:12px;flex-wrap:wrap;">
        <h2 id="sm-groups-heading" style="margin:0;font-size:16px;font-weight:700;">
            {{ __('sanctum-manager::sanctum-manager.scopes.groups_heading') }}
            <span style="color:var(--text-muted);font-weight:500;">({{ count($groups) }})</span>
        </h2>
    </div>

    @if (count($groups) === 0)
        <div class="sm-card">
            <div class="sm-empty">
                <h3>{{ __('sanctum-manager::sanctum-manager.scopes.groups_empty') }}</h3>
            </div>
        </div>
    @else
        <div class="sm-grid-3">
            @foreach ($groups as $group)
                @php
                    $isPersistedGroup = in_array($group->name, $persistedGroups, true);
                    $groupPayload = [
                        'name' => $group->name,
                        'description' => $group->description,
                        'scopes' => array_values($group->scopes),
                    ];
                    $confirmDeleteGroup = __('sanctum-manager::sanctum-manager.scopes.confirm_delete_group', ['name' => $group->name]);
                @endphp
                <article class="sm-card" style="margin-bottom:0;display:flex;flex-direction:column;">
                    <div class="sm-card-header">
                        <div style="min-width:0;">
                            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                                <h3 class="sm-card-title mono">{{ '@' . $group->name }}</h3>
                                @if ($isPersistedGroup)
                                    <span class="sm-badge {{ $dynamicSourceBadge }}" style="padding:2px 7px;font-size:11px;">{{ $dynamicSourceLabel }}</span>
                                @else
                                    <span class="sm-badge sm-badge-neutral" style="padding:2px 7px;font-size:11px;">{{ __('sanctum-manager::sanctum-manager.badges.source_config') }}</span>
                                @endif
                            </div>
                            @if ($group->description !== '')
                                <p class="sm-card-subtitle">{{ $group->description }}</p>
                            @endif
                        </div>
                        <span class="sm-badge sm-badge-accent">{{ count($group->scopes) }}</span>
                    </div>

                    <div class="sm-card-body" style="flex:1;">
                        @if (count($group->scopes) > 0)
                            <div class="sm-chips">
                                @foreach ($group->scopes as $groupScope)
                                    <span class="sm-chip">{{ $groupScope }}</span>
                                @endforeach
                            </div>
                        @else
                            <span class="sm-cell-sub">{{ __('sanctum-manager::sanctum-manager.common.none') }}</span>
                        @endif
                    </div>

                    <div style="padding:11px 18px;border-top:1px solid var(--border);background:var(--surface-subtle);display:flex;align-items:center;justify-content:flex-end;gap:8px;">
                        <button type="button"
                                class="sm-btn sm-btn-sm"
                                data-edit-group
                                data-group="{{ json_encode($groupPayload, JSON_THROW_ON_ERROR) }}">
                            {{ __('sanctum-manager::sanctum-manager.common.edit') }}
                        </button>
                        @if ($isPersistedGroup)
                            <form method="POST"
                                  action="{{ route('sanctum-manager.dashboard.groups.destroy', ['groupName' => $group->name]) }}"
                                  style="margin:0;"
                                  data-confirm-message="{{ $confirmDeleteGroup }}"
                                  onsubmit="return confirm(this.dataset.confirmMessage);">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="sm-btn sm-btn-sm sm-btn-danger-outline">{{ __('sanctum-manager::sanctum-manager.common.delete') }}</button>
                            </form>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</section>

{{-- Registered Scopes Table --}}
<section class="sm-card" aria-labelledby="sm-scopes-heading">
    <div class="sm-card-header">
        <div>
            <h2 class="sm-card-title" id="sm-scopes-heading">{{ __('sanctum-manager::sanctum-manager.scopes.scopes_heading') }} ({{ count($scopes) }})</h2>
        </div>
    </div>

    @if (count($scopes) === 0)
        <div class="sm-empty">
            <h3>{{ __('sanctum-manager::sanctum-manager.scopes.scopes_empty') }}</h3>
        </div>
    @else
        <div class="sm-table-wrap">
            <table class="sm-table">
                <thead>
                    <tr>
                        <th>{{ __('sanctum-manager::sanctum-manager.scopes.col_scope') }}</th>
                        <th>{{ __('sanctum-manager::sanctum-manager.scopes.col_group') }}</th>
                        <th>{{ __('sanctum-manager::sanctum-manager.scopes.col_description') }}</th>
                        <th>{{ __('sanctum-manager::sanctum-manager.scopes.col_implies') }}</th>
                        <th>{{ __('sanctum-manager::sanctum-manager.scopes.col_flags') }}</th>
                        <th>{{ __('sanctum-manager::sanctum-manager.scopes.col_source') }}</th>
                        <th style="text-align:right;">{{ __('sanctum-manager::sanctum-manager.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($scopes as $scope)
                        @php
                            $isPersistedScope = in_array($scope->name, $persistedScopes, true);
                            $scopePayload = [
                                'name' => $scope->name,
                                'description' => $scope->description,
                                'group' => $scope->group ?? '',
                                'implies' => array_values($scope->implies),
                                'is_default' => $scope->isDefault,
                                'is_deprecated' => $scope->isDeprecated,
                            ];
                            $confirmDeleteScope = __('sanctum-manager::sanctum-manager.scopes.confirm_delete_scope', ['name' => $scope->name]);
                        @endphp
                        <tr>
                            <td style="white-space:nowrap;">
                                <span class="sm-chip sm-chip-accent">{{ $scope->name }}</span>
                            </td>
                            <td style="white-space:nowrap;">
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
                                    <span class="sm-cell-sub">—</span>
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
                                        <span class="sm-badge sm-badge-success">{{ __('sanctum-manager::sanctum-manager.badges.default') }}</span>
                                    @endif
                                    @if ($scope->isDeprecated)
                                        <span class="sm-badge sm-badge-warning">{{ __('sanctum-manager::sanctum-manager.badges.deprecated') }}</span>
                                    @endif
                                    @if (! $scope->isDefault && ! $scope->isDeprecated)
                                        <span class="sm-cell-sub">—</span>
                                    @endif
                                </div>
                            </td>
                            <td style="white-space:nowrap;">
                                @if ($isPersistedScope)
                                    <span class="sm-badge {{ $dynamicSourceBadge }}">{{ $dynamicSourceLabel }}</span>
                                @else
                                    <span class="sm-badge sm-badge-neutral">{{ __('sanctum-manager::sanctum-manager.badges.source_config') }}</span>
                                @endif
                            </td>
                            <td style="text-align:right;white-space:nowrap;">
                                <div style="display:inline-flex;align-items:center;gap:6px;">
                                    <button type="button"
                                            class="sm-btn sm-btn-sm"
                                            data-edit-scope
                                            data-scope="{{ json_encode($scopePayload, JSON_THROW_ON_ERROR) }}">
                                        {{ __('sanctum-manager::sanctum-manager.common.edit') }}
                                    </button>
                                    @if ($isPersistedScope)
                                        <form method="POST"
                                              action="{{ route('sanctum-manager.dashboard.scopes.destroy', ['scopeName' => $scope->name]) }}"
                                              style="margin:0;"
                                              data-confirm-message="{{ $confirmDeleteScope }}"
                                              onsubmit="return confirm(this.dataset.confirmMessage);">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="sm-btn sm-btn-sm sm-btn-danger-outline">{{ __('sanctum-manager::sanctum-manager.common.delete') }}</button>
                                        </form>
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

{{-- Existing scope category suggestions --}}
<datalist id="sm-scope-categories">
    @foreach ($scopeCategories as $category)
        <option value="{{ $category }}"></option>
    @endforeach
</datalist>

{{-- Create / Edit Scope Modal --}}
<dialog id="modal-scope" class="sm-dialog" aria-labelledby="modal-scope-title">
    <form method="POST" action="{{ route('sanctum-manager.dashboard.scopes.store') }}" class="sm-dialog-form" id="form-scope">
        @csrf
        <div class="sm-dialog-header">
            <h2 class="sm-dialog-title" id="modal-scope-title">{{ __('sanctum-manager::sanctum-manager.scopes.modal_scope_create') }}</h2>
            <button type="button" class="sm-icon-btn" data-dialog-close aria-label="{{ __('sanctum-manager::sanctum-manager.common.close') }}">&times;</button>
        </div>

        <div class="sm-dialog-body">
            <div class="sm-field-grid">
                <div class="sm-field">
                    <label for="scope-name" class="sm-label">{{ __('sanctum-manager::sanctum-manager.scopes.field_scope_name') }} <span style="color:var(--danger-dot);">*</span></label>
                    <input type="text"
                           id="scope-name"
                           name="name"
                           class="sm-input mono"
                           placeholder="orders:write"
                           required>
                </div>

                <div class="sm-field">
                    <label for="scope-group" class="sm-label">{{ __('sanctum-manager::sanctum-manager.scopes.field_category') }}</label>
                    <input type="text"
                           id="scope-group"
                           name="group"
                           list="sm-scope-categories"
                           class="sm-input mono"
                           placeholder="orders">
                </div>
            </div>

            <div class="sm-field">
                <label for="scope-description" class="sm-label">{{ __('sanctum-manager::sanctum-manager.scopes.field_description') }}</label>
                <input type="text"
                       id="scope-description"
                       name="description"
                       class="sm-input">
            </div>

            @if (count($scopes) > 0)
                <div class="sm-field">
                    <span class="sm-label">{{ __('sanctum-manager::sanctum-manager.scopes.field_implies') }}</span>
                    <div class="sm-check-grid" id="scope-implies-grid">
                        @foreach ($scopes as $existingScope)
                            <label class="sm-check-item">
                                <input type="checkbox" name="implies[]" value="{{ $existingScope->name }}">
                                <span>
                                    <strong class="mono">{{ $existingScope->name }}</strong>
                                    @if ($existingScope->description !== '')
                                        <span class="sm-cell-sub" style="display:block;">{{ $existingScope->description }}</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="sm-field">
                <label for="scope-custom-implies" class="sm-label">{{ __('sanctum-manager::sanctum-manager.scopes.field_custom_implies') }}</label>
                <input type="text"
                       id="scope-custom-implies"
                       name="custom_implies"
                       class="sm-input mono"
                       placeholder="orders:read, inventory:view">
            </div>

            <div class="sm-field-grid" style="margin-top:14px;">
                <label class="sm-check-item" style="padding:10px 12px;border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--surface-subtle);">
                    <input type="checkbox" id="scope-is-default" name="is_default" value="1">
                    <span>{{ __('sanctum-manager::sanctum-manager.scopes.field_is_default') }}</span>
                </label>

                <label class="sm-check-item" style="padding:10px 12px;border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--surface-subtle);">
                    <input type="checkbox" id="scope-is-deprecated" name="is_deprecated" value="1">
                    <span>{{ __('sanctum-manager::sanctum-manager.scopes.field_is_deprecated') }}</span>
                </label>
            </div>
        </div>

        <div class="sm-dialog-footer">
            <button type="button" class="sm-btn" data-dialog-close>{{ __('sanctum-manager::sanctum-manager.common.cancel') }}</button>
            <button type="submit" class="sm-btn sm-btn-primary" id="modal-scope-submit">{{ __('sanctum-manager::sanctum-manager.common.save') }}</button>
        </div>
    </form>
</dialog>

{{-- Create / Edit Scope Group Modal --}}
<dialog id="modal-group" class="sm-dialog" aria-labelledby="modal-group-title">
    <form method="POST" action="{{ route('sanctum-manager.dashboard.groups.store') }}" class="sm-dialog-form" id="form-group">
        @csrf
        <div class="sm-dialog-header">
            <h2 class="sm-dialog-title" id="modal-group-title">{{ __('sanctum-manager::sanctum-manager.scopes.modal_group_create') }}</h2>
            <button type="button" class="sm-icon-btn" data-dialog-close aria-label="{{ __('sanctum-manager::sanctum-manager.common.close') }}">&times;</button>
        </div>

        <div class="sm-dialog-body">
            <div class="sm-field">
                <label for="group-name" class="sm-label">{{ __('sanctum-manager::sanctum-manager.scopes.field_group_name') }} <span style="color:var(--danger-dot);">*</span></label>
                <input type="text"
                       id="group-name"
                       name="name"
                       class="sm-input mono"
                       placeholder="readonly"
                       required>
            </div>

            <div class="sm-field">
                <label for="group-description" class="sm-label">{{ __('sanctum-manager::sanctum-manager.scopes.field_description') }}</label>
                <input type="text"
                       id="group-description"
                       name="description"
                       class="sm-input">
            </div>

            @if (count($scopes) > 0)
                <div class="sm-field">
                    <span class="sm-label">{{ __('sanctum-manager::sanctum-manager.scopes.field_group_scopes') }}</span>
                    <div class="sm-check-grid" id="group-scopes-grid">
                        @foreach ($scopes as $existingScope)
                            <label class="sm-check-item">
                                <input type="checkbox" name="scopes[]" value="{{ $existingScope->name }}">
                                <span>
                                    <strong class="mono">{{ $existingScope->name }}</strong>
                                    @if ($existingScope->description !== '')
                                        <span class="sm-cell-sub" style="display:block;">{{ $existingScope->description }}</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="sm-field" style="margin-bottom:0;">
                <label for="group-custom-scopes" class="sm-label">{{ __('sanctum-manager::sanctum-manager.scopes.field_custom_scopes') }}</label>
                <input type="text"
                       id="group-custom-scopes"
                       name="custom_scopes"
                       class="sm-input mono"
                       placeholder="reports:read, metrics:view">
            </div>
        </div>

        <div class="sm-dialog-footer">
            <button type="button" class="sm-btn" data-dialog-close>{{ __('sanctum-manager::sanctum-manager.common.cancel') }}</button>
            <button type="submit" class="sm-btn sm-btn-primary" id="modal-group-submit">{{ __('sanctum-manager::sanctum-manager.common.save') }}</button>
        </div>
    </form>
</dialog>
@endsection

@push('scripts')
<script>
    (function () {
        var i18n = {
            scopeCreate: @json(__('sanctum-manager::sanctum-manager.scopes.modal_scope_create')),
            scopeEdit: @json(__('sanctum-manager::sanctum-manager.scopes.modal_scope_edit')),
            groupCreate: @json(__('sanctum-manager::sanctum-manager.scopes.modal_group_create')),
            groupEdit: @json(__('sanctum-manager::sanctum-manager.scopes.modal_group_edit'))
        };

        var scopeDialog = document.getElementById('modal-scope');
        var scopeForm = document.getElementById('form-scope');
        var scopeTitle = document.getElementById('modal-scope-title');
        var scopeNameInput = document.getElementById('scope-name');
        var scopeGroupInput = document.getElementById('scope-group');
        var scopeDescInput = document.getElementById('scope-description');
        var scopeCustomImpliesInput = document.getElementById('scope-custom-implies');
        var scopeDefaultInput = document.getElementById('scope-is-default');
        var scopeDeprecatedInput = document.getElementById('scope-is-deprecated');

        var groupDialog = document.getElementById('modal-group');
        var groupForm = document.getElementById('form-group');
        var groupTitle = document.getElementById('modal-group-title');
        var groupNameInput = document.getElementById('group-name');
        var groupDescInput = document.getElementById('group-description');
        var groupCustomScopesInput = document.getElementById('group-custom-scopes');

        function resetScopeModal() {
            if (!scopeForm) return;
            scopeForm.reset();
            scopeTitle.textContent = i18n.scopeCreate;
            scopeForm.querySelectorAll('input[name="implies[]"]').forEach(function (cb) {
                cb.checked = false;
                cb.disabled = false;
            });
        }

        function openScopeEditModal(data) {
            resetScopeModal();
            scopeTitle.textContent = i18n.scopeEdit + ': ' + (data.name || '');
            scopeNameInput.value = data.name || '';
            scopeGroupInput.value = data.group || '';
            scopeDescInput.value = data.description || '';
            scopeDefaultInput.checked = Boolean(data.is_default);
            scopeDeprecatedInput.checked = Boolean(data.is_deprecated);

            var impliesList = Array.isArray(data.implies) ? data.implies : [];
            var matched = {};

            scopeForm.querySelectorAll('input[name="implies[]"]').forEach(function (cb) {
                if (cb.value === data.name) {
                    cb.checked = false;
                    cb.disabled = true;
                    return;
                }
                if (impliesList.indexOf(cb.value) !== -1) {
                    cb.checked = true;
                    matched[cb.value] = true;
                }
            });

            var customImplies = impliesList.filter(function (item) {
                return !matched[item] && item !== data.name;
            });
            scopeCustomImpliesInput.value = customImplies.join(', ');

            scopeDialog.showModal();
        }

        function resetGroupModal() {
            if (!groupForm) return;
            groupForm.reset();
            groupTitle.textContent = i18n.groupCreate;
            groupForm.querySelectorAll('input[name="scopes[]"]').forEach(function (cb) {
                cb.checked = false;
            });
        }

        function openGroupEditModal(data) {
            resetGroupModal();
            groupTitle.textContent = i18n.groupEdit + ': @' + (data.name || '');
            groupNameInput.value = data.name || '';
            groupDescInput.value = data.description || '';

            var scopesList = Array.isArray(data.scopes) ? data.scopes : [];
            var matched = {};

            groupForm.querySelectorAll('input[name="scopes[]"]').forEach(function (cb) {
                if (scopesList.indexOf(cb.value) !== -1) {
                    cb.checked = true;
                    matched[cb.value] = true;
                }
            });

            var customScopes = scopesList.filter(function (item) {
                return !matched[item];
            });
            groupCustomScopesInput.value = customScopes.join(', ');

            groupDialog.showModal();
        }

        document.addEventListener('click', function (event) {
            if (event.target.closest('[data-create-scope]')) {
                resetScopeModal();
                scopeDialog.showModal();
                return;
            }

            var editScopeBtn = event.target.closest('[data-edit-scope]');
            if (editScopeBtn) {
                try {
                    var scopeData = JSON.parse(editScopeBtn.getAttribute('data-scope') || '{}');
                    openScopeEditModal(scopeData);
                } catch (e) {}
                return;
            }

            if (event.target.closest('[data-create-group]')) {
                resetGroupModal();
                groupDialog.showModal();
                return;
            }

            var editGroupBtn = event.target.closest('[data-edit-group]');
            if (editGroupBtn) {
                try {
                    var groupData = JSON.parse(editGroupBtn.getAttribute('data-group') || '{}');
                    openGroupEditModal(groupData);
                } catch (e) {}
            }
        });
    })();
</script>
@endpush
