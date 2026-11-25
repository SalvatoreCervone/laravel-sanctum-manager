@extends('sanctum-manager::mail.layout')

@section('title', __('sanctum-manager::sanctum-manager.mail.issued_heading'))

@section('badge')
    <span style="display:inline-block;padding:4px 10px;border-radius:9999px;background-color:#10b981;color:#ffffff;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;">
        {{ __('sanctum-manager::sanctum-manager.badges.active') }}
    </span>
@endsection

@section('heading', __('sanctum-manager::sanctum-manager.mail.issued_heading'))

@section('content')
    <p style="margin:0 0 20px 0;font-size:14px;color:#475569;">
        {{ __('sanctum-manager::sanctum-manager.mail.issued_intro') }}
        {{ __('sanctum-manager::sanctum-manager.mail.secret_omitted') }}
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;font-size:13px;">
        <tr style="border-bottom:1px solid #e2e8f0;">
            <td style="padding:10px 14px;background-color:#f8fafc;color:#64748b;font-weight:600;width:36%;">{{ __('sanctum-manager::sanctum-manager.mail.label_token_name') }}</td>
            <td style="padding:10px 14px;color:#0f172a;font-weight:600;">{{ $tokenName }}</td>
        </tr>
        @if ($ownerName !== null || $ownerEmail !== null)
            <tr style="border-bottom:1px solid #e2e8f0;">
                <td style="padding:10px 14px;background-color:#f8fafc;color:#64748b;font-weight:600;">{{ __('sanctum-manager::sanctum-manager.mail.label_owner') }}</td>
                <td style="padding:10px 14px;color:#0f172a;">
                    @if ($ownerName !== null && $ownerEmail !== null)
                        {{ $ownerName }} ({{ $ownerEmail }})
                    @else
                        {{ $ownerName ?? $ownerEmail }}
                    @endif
                </td>
            </tr>
        @endif
        <tr style="border-bottom:1px solid #e2e8f0;">
            <td style="padding:10px 14px;background-color:#f8fafc;color:#64748b;font-weight:600;">{{ __('sanctum-manager::sanctum-manager.mail.label_scopes') }}</td>
            <td style="padding:10px 14px;color:#0f172a;">
                @if ($scopes === [])
                    <span style="color:#64748b;">{{ __('sanctum-manager::sanctum-manager.common.none') }}</span>
                @else
                    @foreach ($scopes as $scope)
                        <span style="display:inline-block;margin:2px 4px 2px 0;padding:2px 8px;border-radius:4px;background-color:#eef2ff;color:#3730a3;font-family:monospace;font-size:12px;">{{ $scope }}</span>
                    @endforeach
                @endif
            </td>
        </tr>
        <tr style="border-bottom:1px solid #e2e8f0;">
            <td style="padding:10px 14px;background-color:#f8fafc;color:#64748b;font-weight:600;">{{ __('sanctum-manager::sanctum-manager.mail.label_expires_at') }}</td>
            <td style="padding:10px 14px;color:#0f172a;">{{ $expiresAt ?? __('sanctum-manager::sanctum-manager.common.never') }}</td>
        </tr>
        @if ($allowedIps !== [])
            <tr style="border-bottom:1px solid #e2e8f0;">
                <td style="padding:10px 14px;background-color:#f8fafc;color:#64748b;font-weight:600;">{{ __('sanctum-manager::sanctum-manager.mail.label_allowed_ips') }}</td>
                <td style="padding:10px 14px;color:#0f172a;font-family:monospace;font-size:12px;">{{ implode(', ', $allowedIps) }}</td>
            </tr>
        @endif
        @if ($allowedOrigins !== [])
            <tr style="border-bottom:1px solid #e2e8f0;">
                <td style="padding:10px 14px;background-color:#f8fafc;color:#64748b;font-weight:600;">{{ __('sanctum-manager::sanctum-manager.mail.label_allowed_origins') }}</td>
                <td style="padding:10px 14px;color:#0f172a;font-family:monospace;font-size:12px;">{{ implode(', ', $allowedOrigins) }}</td>
            </tr>
        @endif
        @if ($maxUses !== null)
            <tr style="border-bottom:1px solid #e2e8f0;">
                <td style="padding:10px 14px;background-color:#f8fafc;color:#64748b;font-weight:600;">{{ __('sanctum-manager::sanctum-manager.mail.label_max_uses') }}</td>
                <td style="padding:10px 14px;color:#0f172a;">{{ $maxUses }}</td>
            </tr>
        @endif
        <tr>
            <td style="padding:10px 14px;background-color:#f8fafc;color:#64748b;font-weight:600;">{{ __('sanctum-manager::sanctum-manager.mail.label_occurred_at') }}</td>
            <td style="padding:10px 14px;color:#0f172a;font-family:monospace;font-size:12px;">{{ $occurredAt }}</td>
        </tr>
    </table>
@endsection

@section('security_notice')
    {{ __('sanctum-manager::sanctum-manager.mail.security_notice') }}
@endsection
