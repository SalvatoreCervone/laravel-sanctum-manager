@extends('sanctum-manager::mail.layout')

@section('title', 'Personal Access Token Issued')

@section('badge')
    <span style="display:inline-block;padding:4px 10px;border-radius:9999px;background-color:#10b981;color:#ffffff;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;">
        Issued
    </span>
@endsection

@section('heading', 'A new API token was issued')

@section('content')
    <p style="margin:0 0 20px 0;font-size:14px;color:#475569;">
        A new personal access token has been created. For your security, the plain-text secret is never included in this notification.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;font-size:13px;">
        <tr style="border-bottom:1px solid #e2e8f0;">
            <td style="padding:10px 14px;background-color:#f8fafc;color:#64748b;font-weight:600;width:36%;">Token Name</td>
            <td style="padding:10px 14px;color:#0f172a;font-weight:600;">{{ $tokenName }}</td>
        </tr>
        @if ($ownerName !== null || $ownerEmail !== null)
            <tr style="border-bottom:1px solid #e2e8f0;">
                <td style="padding:10px 14px;background-color:#f8fafc;color:#64748b;font-weight:600;">Owner</td>
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
            <td style="padding:10px 14px;background-color:#f8fafc;color:#64748b;font-weight:600;">Scopes</td>
            <td style="padding:10px 14px;color:#0f172a;">
                @if ($scopes === [])
                    <span style="color:#64748b;">None</span>
                @else
                    @foreach ($scopes as $scope)
                        <span style="display:inline-block;margin:2px 4px 2px 0;padding:2px 8px;border-radius:4px;background-color:#eef2ff;color:#3730a3;font-family:monospace;font-size:12px;">{{ $scope }}</span>
                    @endforeach
                @endif
            </td>
        </tr>
        <tr style="border-bottom:1px solid #e2e8f0;">
            <td style="padding:10px 14px;background-color:#f8fafc;color:#64748b;font-weight:600;">Expires At</td>
            <td style="padding:10px 14px;color:#0f172a;">{{ $expiresAt ?? 'Never' }}</td>
        </tr>
        @if ($allowedIps !== [])
            <tr style="border-bottom:1px solid #e2e8f0;">
                <td style="padding:10px 14px;background-color:#f8fafc;color:#64748b;font-weight:600;">Allowed IPs</td>
                <td style="padding:10px 14px;color:#0f172a;font-family:monospace;font-size:12px;">{{ implode(', ', $allowedIps) }}</td>
            </tr>
        @endif
        @if ($allowedOrigins !== [])
            <tr style="border-bottom:1px solid #e2e8f0;">
                <td style="padding:10px 14px;background-color:#f8fafc;color:#64748b;font-weight:600;">Allowed Origins</td>
                <td style="padding:10px 14px;color:#0f172a;font-family:monospace;font-size:12px;">{{ implode(', ', $allowedOrigins) }}</td>
            </tr>
        @endif
        @if ($maxUses !== null)
            <tr style="border-bottom:1px solid #e2e8f0;">
                <td style="padding:10px 14px;background-color:#f8fafc;color:#64748b;font-weight:600;">Max Uses</td>
                <td style="padding:10px 14px;color:#0f172a;">{{ $maxUses }}</td>
            </tr>
        @endif
        <tr>
            <td style="padding:10px 14px;background-color:#f8fafc;color:#64748b;font-weight:600;">Occurred At</td>
            <td style="padding:10px 14px;color:#0f172a;font-family:monospace;font-size:12px;">{{ $occurredAt }}</td>
        </tr>
    </table>
@endsection

@section('security_notice')
    If you did not perform this action, revoke the token immediately and review your account security.
@endsection
