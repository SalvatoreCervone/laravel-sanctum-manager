@extends('sanctum-manager::mail.layout')

@section('title', __('sanctum-manager::sanctum-manager.mail.test_heading'))

@section('badge')
    <span style="display:inline-block;padding:4px 10px;border-radius:9999px;background-color:#6366f1;color:#ffffff;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;">
        {{ __('sanctum-manager::sanctum-manager.mail.test_subject') }}
    </span>
@endsection

@section('heading', __('sanctum-manager::sanctum-manager.mail.test_heading'))

@section('content')
    <p style="margin:0 0 20px 0;font-size:14px;color:#475569;">
        {{ __('sanctum-manager::sanctum-manager.mail.test_intro') }}
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;font-size:13px;">
        <tr style="border-bottom:1px solid #e2e8f0;">
            <td style="padding:10px 14px;background-color:#f8fafc;color:#64748b;font-weight:600;width:36%;">{{ __('sanctum-manager::sanctum-manager.mail.label_recipient') }}</td>
            <td style="padding:10px 14px;color:#0f172a;">{{ $recipientEmail }}</td>
        </tr>
        <tr>
            <td style="padding:10px 14px;background-color:#f8fafc;color:#64748b;font-weight:600;">{{ __('sanctum-manager::sanctum-manager.mail.label_occurred_at') }}</td>
            <td style="padding:10px 14px;color:#0f172a;font-family:monospace;font-size:12px;">{{ $sentAt }}</td>
        </tr>
    </table>
@endsection
