@extends('sanctum-manager::dashboard.layout')

@section('title', __('sanctum-manager::sanctum-manager.mail_page.title'))

@section('content')
@php
    $mailIsEnabled = (bool) ($mailConfig['enabled'] ?? false);
    $events = is_array($mailConfig['events'] ?? null) ? $mailConfig['events'] : [];
    $notifyOwner = (bool) ($mailConfig['notify_token_owner'] ?? false);
    $recipients = is_array($mailConfig['recipients'] ?? null) ? $mailConfig['recipients'] : [];
    $subjectPrefix = (string) ($mailConfig['subject_prefix'] ?? '[Sanctum Manager]');
    $queueEnabled = (bool) ($mailConfig['queue'] ?? false);

    $gateName = (string) ($gateConfig['gate'] ?? 'viewSanctumManager');
    $allowInLocal = (bool) ($gateConfig['allow_in_local'] ?? true);
    $allowedEmails = is_array($gateConfig['allowed_emails'] ?? null) ? $gateConfig['allowed_emails'] : [];
@endphp

<div class="sm-page-head">
    <div>
        <h1 class="sm-page-title">{{ __('sanctum-manager::sanctum-manager.mail_page.title') }}</h1>
        <p class="sm-page-subtitle">{{ __('sanctum-manager::sanctum-manager.mail_page.subtitle') }}</p>
    </div>
</div>

<div class="sm-grid-2">
    {{-- Card 1: Mail Notification Status & Active Events --}}
    <section class="sm-card" style="margin-bottom:0;" aria-labelledby="sm-mail-status-heading">
        <div class="sm-card-header">
            <div>
                <h2 class="sm-card-title" id="sm-mail-status-heading">{{ __('sanctum-manager::sanctum-manager.mail_page.mail_card_title') }}</h2>
            </div>
            <span class="sm-badge {{ $mailIsEnabled ? 'sm-badge-success' : 'sm-badge-neutral' }}">
                <span class="sm-badge-dot" aria-hidden="true"></span>
                {{ $mailIsEnabled ? __('sanctum-manager::sanctum-manager.common.enabled') : __('sanctum-manager::sanctum-manager.common.disabled') }}
            </span>
        </div>

        <div class="sm-card-body">
            <dl class="sm-kv-list">
                <div class="sm-kv-row">
                    <dt class="sm-kv-key">{{ __('sanctum-manager::sanctum-manager.mail_page.status') }}</dt>
                    <dd class="sm-kv-val" style="margin:0;">
                        <span class="sm-badge {{ $mailIsEnabled ? 'sm-badge-success' : 'sm-badge-neutral' }}">
                            {{ $mailIsEnabled ? __('sanctum-manager::sanctum-manager.common.enabled') : __('sanctum-manager::sanctum-manager.common.disabled') }}
                        </span>
                    </dd>
                </div>

                <div class="sm-kv-row">
                    <dt class="sm-kv-key">{{ __('sanctum-manager::sanctum-manager.mail_page.events') }}</dt>
                    <dd class="sm-kv-val" style="margin:0;">
                        <div class="sm-chips" style="justify-content:flex-end;">
                            <span class="sm-badge {{ ! empty($events['issued']) ? 'sm-badge-success' : 'sm-badge-neutral' }}">
                                <span class="sm-badge-dot" aria-hidden="true"></span>
                                {{ __('sanctum-manager::sanctum-manager.mail_page.event_issued') }}
                            </span>
                            <span class="sm-badge {{ ! empty($events['revoked']) ? 'sm-badge-success' : 'sm-badge-neutral' }}">
                                <span class="sm-badge-dot" aria-hidden="true"></span>
                                {{ __('sanctum-manager::sanctum-manager.mail_page.event_revoked') }}
                            </span>
                            <span class="sm-badge {{ ! empty($events['rotated']) ? 'sm-badge-success' : 'sm-badge-neutral' }}">
                                <span class="sm-badge-dot" aria-hidden="true"></span>
                                {{ __('sanctum-manager::sanctum-manager.mail_page.event_rotated') }}
                            </span>
                        </div>
                    </dd>
                </div>

                <div class="sm-kv-row">
                    <dt class="sm-kv-key">{{ __('sanctum-manager::sanctum-manager.mail_page.notify_owner') }}</dt>
                    <dd class="sm-kv-val" style="margin:0;">
                        <span class="sm-badge {{ $notifyOwner ? 'sm-badge-info' : 'sm-badge-neutral' }}">
                            {{ $notifyOwner ? __('sanctum-manager::sanctum-manager.common.yes') : __('sanctum-manager::sanctum-manager.common.no') }}
                        </span>
                    </dd>
                </div>

                <div class="sm-kv-row">
                    <dt class="sm-kv-key">{{ __('sanctum-manager::sanctum-manager.mail_page.recipients') }}</dt>
                    <dd class="sm-kv-val" style="margin:0;">
                        @if (count($recipients) > 0)
                            <div class="sm-chips" style="justify-content:flex-end;">
                                @foreach ($recipients as $recipient)
                                    <span class="sm-chip">{{ $recipient }}</span>
                                @endforeach
                            </div>
                        @else
                            <span class="sm-cell-sub">{{ __('sanctum-manager::sanctum-manager.common.none') }}</span>
                        @endif
                    </dd>
                </div>

                <div class="sm-kv-row">
                    <dt class="sm-kv-key">{{ __('sanctum-manager::sanctum-manager.mail_page.delivery') }}</dt>
                    <dd class="sm-kv-val" style="margin:0;">
                        <span class="sm-badge {{ $queueEnabled ? 'sm-badge-accent' : 'sm-badge-neutral' }}">
                            {{ $queueEnabled ? __('sanctum-manager::sanctum-manager.mail_page.delivery_queued') : __('sanctum-manager::sanctum-manager.mail_page.delivery_sync') }}
                        </span>
                    </dd>
                </div>

                <div class="sm-kv-row">
                    <dt class="sm-kv-key">{{ __('sanctum-manager::sanctum-manager.mail_page.subject_prefix') }}</dt>
                    <dd class="sm-kv-val mono" style="margin:0;">{{ $subjectPrefix }}</dd>
                </div>
            </dl>

            <div style="margin-top:16px;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                    <span class="sm-label" style="margin-bottom:0;"><code>.env</code></span>
                    <button type="button" class="sm-btn sm-btn-sm" data-copy-target="sm-env-snippet">{{ __('sanctum-manager::sanctum-manager.common.copy') }}</button>
                </div>
                <pre class="sm-pre" id="sm-env-snippet">SANCTUM_MANAGER_MAIL_ENABLED=true
SANCTUM_MANAGER_MAIL_NOTIFY_OWNER=true
SANCTUM_MANAGER_MAIL_RECIPIENTS="security@example.com"
SANCTUM_MANAGER_MAIL_QUEUE=false</pre>
            </div>
        </div>
    </section>

    <div style="display:flex;flex-direction:column;gap:18px;">
        {{-- Card 2: Send Test Notification Email --}}
        <section class="sm-card" style="margin-bottom:0;" aria-labelledby="sm-mail-test-heading">
            <div class="sm-card-header">
                <div>
                    <h2 class="sm-card-title" id="sm-mail-test-heading">{{ __('sanctum-manager::sanctum-manager.mail_page.test_card_title') }}</h2>
                </div>
            </div>

            <div class="sm-card-body">
                <form method="POST" action="{{ route('sanctum-manager.dashboard.mail.test') }}">
                    @csrf
                    <div class="sm-field">
                        <label for="sm-test-email" class="sm-label">{{ __('sanctum-manager::sanctum-manager.mail_page.test_email_label') }} <span style="color:var(--danger-dot);">*</span></label>
                        <input type="email"
                               id="sm-test-email"
                               name="email"
                               class="sm-input"
                               value="{{ old('email', auth()->user()?->email ?? '') }}"
                               placeholder="admin@example.com"
                               required>
                    </div>
                    <button type="submit" class="sm-btn sm-btn-primary">
                        {{ __('sanctum-manager::sanctum-manager.mail_page.test_submit') }}
                    </button>
                </form>
            </div>
        </section>

        {{-- Card 3: Dashboard Access Gate Status --}}
        <section class="sm-card" style="margin-bottom:0;" aria-labelledby="sm-gate-heading">
            <div class="sm-card-header">
                <div>
                    <h2 class="sm-card-title" id="sm-gate-heading">{{ __('sanctum-manager::sanctum-manager.mail_page.gate_card_title') }}</h2>
                </div>
                <span class="sm-badge sm-badge-info mono">{{ $gateName }}</span>
            </div>

            <div class="sm-card-body">
                <dl class="sm-kv-list">
                    <div class="sm-kv-row">
                        <dt class="sm-kv-key">{{ __('sanctum-manager::sanctum-manager.mail_page.gate_name') }}</dt>
                        <dd class="sm-kv-val mono" style="margin:0;">{{ $gateName }}</dd>
                    </div>

                    <div class="sm-kv-row">
                        <dt class="sm-kv-key">{{ __('sanctum-manager::sanctum-manager.mail_page.allow_local') }}</dt>
                        <dd class="sm-kv-val" style="margin:0;">
                            <span class="sm-badge {{ $allowInLocal ? 'sm-badge-success' : 'sm-badge-warning' }}">
                                {{ $allowInLocal ? __('sanctum-manager::sanctum-manager.common.yes') : __('sanctum-manager::sanctum-manager.common.no') }}
                            </span>
                        </dd>
                    </div>

                    <div class="sm-kv-row">
                        <dt class="sm-kv-key">{{ __('sanctum-manager::sanctum-manager.mail_page.allowed_emails') }}</dt>
                        <dd class="sm-kv-val" style="margin:0;">
                            @if (count($allowedEmails) > 0)
                                <div class="sm-chips" style="justify-content:flex-end;">
                                    @foreach ($allowedEmails as $allowedEmail)
                                        <span class="sm-chip">{{ $allowedEmail }}</span>
                                    @endforeach
                                </div>
                            @else
                                <span class="sm-cell-sub">{{ __('sanctum-manager::sanctum-manager.common.none') }}</span>
                            @endif
                        </dd>
                    </div>
                </dl>

                <div style="margin-top:16px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                        <span class="sm-label" style="margin-bottom:0;"><code>AppServiceProvider::boot()</code></span>
                        <button type="button" class="sm-btn sm-btn-sm" data-copy-target="sm-gate-snippet">{{ __('sanctum-manager::sanctum-manager.common.copy') }}</button>
                    </div>
                    <pre class="sm-pre" id="sm-gate-snippet">use Illuminate\Support\Facades\Gate;

Gate::define('{{ $gateName }}', function ($user): bool {
    return in_array($user->email, [
        'admin@example.com',
    ], true);
});</pre>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection
