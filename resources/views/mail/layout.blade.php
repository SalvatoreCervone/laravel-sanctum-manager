<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Sanctum Manager Notification')</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#0f172a;line-height:1.5;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background-color:#ffffff;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;">
                    {{-- Header --}}
                    <tr>
                        <td style="background-color:#0f172a;padding:24px 32px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="color:#ffffff;font-size:18px;font-weight:700;letter-spacing:-0.01em;">
                                        {{ config('app.name', 'Laravel') }}
                                        <span style="color:#94a3b8;font-weight:400;font-size:14px;"> &middot; Sanctum Manager</span>
                                    </td>
                                    @hasSection('badge')
                                        <td align="right">
                                            @yield('badge')
                                        </td>
                                    @endif
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding:32px;">
                            <h1 style="margin:0 0 12px 0;font-size:20px;font-weight:700;color:#0f172a;">
                                @yield('heading')
                            </h1>

                            @yield('content')

                            @hasSection('security_notice')
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:24px;background-color:#fef2f2;border:1px solid #fecaca;border-radius:8px;">
                                    <tr>
                                        <td style="padding:14px 16px;font-size:13px;color:#991b1b;">
                                            <strong style="display:block;margin-bottom:4px;">Security Notice</strong>
                                            @yield('security_notice')
                                        </td>
                                    </tr>
                                </table>
                            @endif
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="background-color:#f8fafc;border-top:1px solid #e2e8f0;padding:18px 32px;font-size:12px;color:#64748b;">
                            This is an automated security notification from <strong>{{ config('app.name', 'Laravel') }}</strong>.
                            Plain-text token secrets are never included in emails.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
