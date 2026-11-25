@extends('sanctum-manager::mail.layout')

@section('title', 'Personal Access Token Rotated')

@section('badge')
    <span style="display:inline-block;padding:4px 10px;border-radius:9999px;background-color:#f59e0b;color:#ffffff;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;">
        Rotated
    </span>
@endsection

@section('heading', 'An API token was rotated')

@section('content')
    <p style="margin:0 0 20px 0;font-size:14px;color:#475569;">
        A personal access token has been rotated. Update your clients with the new token before the grace period expires.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;font-size:13px;">
        <tr style="border-bottom:1px solid #e2e8f0;">
            <td style="padding:10px 14px;background-color:#f8fafc;color:#64748b;font-weight:600;width:36%;">Previous Token</td>
            <td style="padding:10px 14px;color:#0f172a;">{{ $oldTokenName }}</td>
        </tr>
        <tr style="border-bottom:1px solid #e2e8f0;">
            <td style="padding:10px 14px;background-color:#f8fafc;color:#64748b;font-weight:600;">New Token</td>
            <td style="padding:10px 14px;color:#0f172a;font-weight:600;">{{ $newTokenName }}</td>
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
            <td style="padding:10px 14px;background-color:#f8fafc;color:#64748b;font-weight:600;">Grace Period Ends</td>
            <td style="padding:10px 14px;color:#0f172a;font-family:monospace;font-size:12px;">{{ $graceExpiresAt }}</td>
        </tr>
        <tr>
            <td style="padding:10px 14px;background-color:#f8fafc;color:#64748b;font-weight:600;">Occurred At</td>
            <td style="padding:10px 14px;color:#0f172a;font-family:monospace;font-size:12px;">{{ $occurredAt }}</td>
        </tr>
    </table>
@endsection

@section('security_notice')
    If you did not perform this action, revoke the new token immediately and review your account security.
@endsection
