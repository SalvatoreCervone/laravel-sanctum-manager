@extends('sanctum-manager::mail.layout')

@section('title', 'Personal Access Token Revoked')

@section('badge')
    <span style="display:inline-block;padding:4px 10px;border-radius:9999px;background-color:#ef4444;color:#ffffff;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;">
        Revoked
    </span>
@endsection

@section('heading', 'An API token was revoked')

@section('content')
    <p style="margin:0 0 20px 0;font-size:14px;color:#475569;">
        The following personal access token has been revoked and can no longer be used to authenticate API requests.
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
            <td style="padding:10px 14px;background-color:#f8fafc;color:#64748b;font-weight:600;">Reason</td>
            <td style="padding:10px 14px;color:#0f172a;">{{ $reason ?? 'Not specified' }}</td>
        </tr>
        <tr>
            <td style="padding:10px 14px;background-color:#f8fafc;color:#64748b;font-weight:600;">Occurred At</td>
            <td style="padding:10px 14px;color:#0f172a;font-family:monospace;font-size:12px;">{{ $occurredAt }}</td>
        </tr>
    </table>
@endsection

@section('security_notice')
    If you did not perform this action, review your account activity and active tokens immediately.
@endsection
