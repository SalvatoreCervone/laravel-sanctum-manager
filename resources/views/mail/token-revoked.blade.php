@extends('sanctum-manager::mail.layout')

@section('title', __('sanctum-manager::sanctum-manager.mail.revoked_heading'))

@section('badge')
    <span style="display:inline-block;padding:4px 10px;border-radius:9999px;background-color:#ef4444;color:#ffffff;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;">
        {{ __('sanctum-manager::sanctum-manager.badges.revoked') }}
    </span>
@endsection

@section('heading', __('sanctum-manager::sanctum-manager.mail.revoked_heading'))

@section('content')
    <p style="margin:0 0 20px 0;font-size:14px;color:#475569;">
        {{ __('sanctum-manager::sanctum-manager.mail.revoked_intro') }}
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
            <td style="padding:10px 14px;background-color:#f8fafc;color:#64748b;font-weight:600;">{{ __('sanctum-manager::sanctum-manager.mail.label_reason') }}</td>
            <td style="padding:10px 14px;color:#0f172a;">{{ $reason ?? __('sanctum-manager::sanctum-manager.common.none') }}</td>
        </tr>
        <tr>
            <td style="padding:10px 14px;background-color:#f8fafc;color:#64748b;font-weight:600;">{{ __('sanctum-manager::sanctum-manager.mail.label_occurred_at') }}</td>
            <td style="padding:10px 14px;color:#0f172a;font-family:monospace;font-size:12px;">{{ $occurredAt }}</td>
        </tr>
    </table>
@endsection

@section('security_notice')
    {{ __('sanctum-manager::sanctum-manager.mail.security_notice') }}
@endsection
