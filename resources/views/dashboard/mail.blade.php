@extends('sanctum-manager::dashboard.layout')

@section('title', 'Mail & Access')

@section('content')
@php
    $mailIsEnabled = (bool) ($mailConfig['enabled'] ?? false);
    $events = is_array($mailConfig['events'] ?? null) ? $mailConfig['events'] : [];
    $notifyOwner = (bool) ($mailConfig['notify_token_owner'] ?? false);
    $recipients = is_array($mailConfig['recipients'] ?? null) ? $mailConfig['recipients'] : [];
    $fromAddress = $mailConfig['from_address'] ?? null;
    $fromName = $mailConfig['from_name'] ?? null;
    $subjectPrefix = (string) ($mailConfig['subject_prefix'] ?? '[Sanctum Manager]');
    $queueEnabled = (bool) ($mailConfig['queue'] ?? false);

    $gateName = (string) ($gateConfig['gate'] ?? 'viewSanctumManager');
    $allowInLocal = (bool) ($gateConfig['allow_in_local'] ?? true);
    $allowedEmails = is_array($gateConfig['allowed_emails'] ?? null) ? $gateConfig['allowed_emails'] : [];
@endphp

<div class="sm-page-head">
    <div>
        <h1 class="sm-page-title">Mail &amp; Access</h1>
        <p class="sm-page-subtitle">Lifecycle email notification settings, test mail delivery, and dashboard authorization gate.</p>
    </div>
</div>

<div class="sm-grid-2">
    {{-- Card 1: Mail Notification Status & Active Events --}}
    <section class="sm-card" style="margin-bottom:0;" aria-labelledby="sm-mail-status-heading">
        <div class="sm-card-header">
            <div>
                <h2 class="sm-card-title" id="sm-mail-status-heading">Mail Notifications</h2>
                <p class="sm-card-subtitle">Automated alerts for token lifecycle events.</p>
            </div>
            <span class="sm-badge {{ $mailIsEnabled ? 'sm-badge-success' : 'sm-badge-neutral' }}">
                <span class="sm-badge-dot" aria-hidden="true"></span>
                {{ $mailIsEnabled ? 'Enabled' : 'Disabled' }}
            </span>
        </div>

        <div class="sm-card-body">
            <dl class="sm-kv-list">
                <div class="sm-kv-row">
                    <dt class="sm-kv-key">Active Events</dt>
                    <dd class="sm-kv-val" style="margin:0;">
                        <div class="sm-chips" style="justify-content:flex-end;">
                            <span class="sm-badge {{ ! empty($events['issued']) ? 'sm-badge-success' : 'sm-badge-neutral' }}">
                                <span class="sm-badge-dot" aria-hidden="true"></span>
                                TokenIssued
                            </span>
                            <span class="sm-badge {{ ! empty($events['revoked']) ? 'sm-badge-success' : 'sm-badge-neutral' }}">
                                <span class="sm-badge-dot" aria-hidden="true"></span>
                                TokenRevoked
                            </span>
                            <span class="sm-badge {{ ! empty($events['rotated']) ? 'sm-badge-success' : 'sm-badge-neutral' }}">
                                <span class="sm-badge-dot" aria-hidden="true"></span>
                                TokenRotated
                            </span>
                        </div>
                    </dd>
                </div>

                <div class="sm-kv-row">
                    <dt class="sm-kv-key">Notify Token Owner</dt>
                    <dd class="sm-kv-val" style="margin:0;">
                        <span class="sm-badge {{ $notifyOwner ? 'sm-badge-info' : 'sm-badge-neutral' }}">
                            {{ $notifyOwner ? 'Yes' : 'No' }}
                        </span>
                    </dd>
                </div>

                <div class="sm-kv-row">
                    <dt class="sm-kv-key">Static Recipients</dt>
                    <dd class="sm-kv-val" style="margin:0;">
                        @if (count($recipients) > 0)
                            <div class="sm-chips" style="justify-content:flex-end;">
                                @foreach ($recipients as $recipient)
                                    <span class="sm-chip">{{ $recipient }}</span>
                                @endforeach
                            </div>
                        @else
                            <span class="sm-cell-sub">None configured</span>
                        @endif
                    </dd>
                </div>

                <div class="sm-kv-row">
                    <dt class="sm-kv-key">Delivery Mode</dt>
                    <dd class="sm-kv-val" style="margin:0;">
                        <span class="sm-badge {{ $queueEnabled ? 'sm-badge-accent' : 'sm-badge-neutral' }}">
                            {{ $queueEnabled ? 'Queued' : 'Synchronous' }}
                        </span>
                    </dd>
                </div>

                <div class="sm-kv-row">
                    <dt class="sm-kv-key">From Address / Name</dt>
                    <dd class="sm-kv-val mono" style="margin:0;">
                        {{ $fromAddress ?: config('mail.from.address', 'Default app mailer') }}
                        @if ($fromName)
                            ({{ $fromName }})
                        @endif
                    </dd>
                </div>

                <div class="sm-kv-row">
                    <dt class="sm-kv-key">Subject Prefix</dt>
                    <dd class="sm-kv-val mono" style="margin:0;">{{ $subjectPrefix }}</dd>
                </div>
            </dl>

            <div style="margin-top:16px;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                    <span class="sm-label" style="margin-bottom:0;">Example <code>.env</code> Configuration</span>
                    <button type="button" class="sm-btn sm-btn-sm" data-copy-target="sm-env-snippet">Copy</button>
                </div>
                <pre class="sm-pre" id="sm-env-snippet">SANCTUM_MANAGER_MAIL_ENABLED=true
SANCTUM_MANAGER_MAIL_NOTIFY_OWNER=true
SANCTUM_MANAGER_MAIL_RECIPIENTS="security@example.com"
SANCTUM_MANAGER_MAIL_QUEUE=false</pre>
            </div>
        </div>
    </section>

    {{-- Card 2: Send Test Notification Email --}}
    <div style="display:flex;flex-direction:column;gap:18px;">
        <section class="sm-card" style="margin-bottom:0;" aria-labelledby="sm-mail-test-heading">
            <div class="sm-card-header">
                <div>
                    <h2 class="sm-card-title" id="sm-mail-test-heading">Send Test Notification Email</h2>
                    <p class="sm-card-subtitle">Verify your Laravel mailer configuration by dispatching a test email.</p>
                </div>
            </div>

            <div class="sm-card-body">
                <form method="POST" action="{{ route('sanctum-manager.dashboard.mail.test') }}">
                    @csrf
                    <div class="sm-field">
                        <label for="sm-test-email" class="sm-label">Recipient Email Address <span style="color:var(--danger-dot);">*</span></label>
                        <input type="email"
                               id="sm-test-email"
                               name="email"
                               class="sm-input"
                               value="{{ old('email', auth()->user()?->email ?? '') }}"
                               placeholder="admin@example.com"
                               required>
                        <p class="sm-help">Sends a sample Sanctum Manager notification using your active Laravel mail transport.</p>
                    </div>
                    <button type="submit" class="sm-btn sm-btn-primary">
                        Send Test Email
                    </button>
                </form>
            </div>
        </section>

        {{-- Card 3: Dashboard Access Gate Status --}}
        <section class="sm-card" style="margin-bottom:0;" aria-labelledby="sm-gate-heading">
            <div class="sm-card-header">
                <div>
                    <h2 class="sm-card-title" id="sm-gate-heading">Dashboard Access Gate</h2>
                    <p class="sm-card-subtitle">Authorization policy protecting the Sanctum Manager UI.</p>
                </div>
                <span class="sm-badge sm-badge-info mono">{{ $gateName }}</span>
            </div>

            <div class="sm-card-body">
                <dl class="sm-kv-list">
                    <div class="sm-kv-row">
                        <dt class="sm-kv-key">Gate Name</dt>
                        <dd class="sm-kv-val mono" style="margin:0;">{{ $gateName }}</dd>
                    </div>

                    <div class="sm-kv-row">
                        <dt class="sm-kv-key">Allow in Local Environment</dt>
                        <dd class="sm-kv-val" style="margin:0;">
                            <span class="sm-badge {{ $allowInLocal ? 'sm-badge-success' : 'sm-badge-warning' }}">
                                {{ $allowInLocal ? 'Allowed' : 'Restricted' }}
                            </span>
                        </dd>
                    </div>

                    <div class="sm-kv-row">
                        <dt class="sm-kv-key">Allowed Emails</dt>
                        <dd class="sm-kv-val" style="margin:0;">
                            @if (count($allowedEmails) > 0)
                                <div class="sm-chips" style="justify-content:flex-end;">
                                    @foreach ($allowedEmails as $allowedEmail)
                                        <span class="sm-chip">{{ $allowedEmail }}</span>
                                    @endforeach
                                </div>
                            @else
                                <span class="sm-cell-sub">None (using custom gate or local bypass)</span>
                            @endif
                        </dd>
                    </div>
                </dl>

                <div style="margin-top:16px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                        <span class="sm-label" style="margin-bottom:0;">Custom Gate in <code>AppServiceProvider::boot()</code></span>
                        <button type="button" class="sm-btn sm-btn-sm" data-copy-target="sm-gate-snippet">Copy</button>
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
