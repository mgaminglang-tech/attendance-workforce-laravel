<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Set up your workforce account</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9; color: #334155; font-family: Arial, Helvetica, sans-serif; -webkit-text-size-adjust: 100%;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f1f5f9" style="width: 100%; background-color: #f1f5f9;">
        <tr>
            <td align="center" style="padding: 32px 16px;">
                <!--[if mso]>
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"><tr><td>
                <![endif]-->
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="width: 100%; max-width: 600px; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px;">
                    <tr>
                        <td bgcolor="#142d45" style="padding: 28px 24px; background-color: #142d45; border-top: 4px solid #0f766e; border-radius: 8px 8px 0 0;">
                            <p style="margin: 0 0 8px; color: #ffffff; font-size: 28px; font-weight: bold; line-height: 36px;">Workforce</p>
                            <p style="margin: 0; color: #d4e4ee; font-size: 14px; line-height: 22px;">Attendance &amp; Workforce Management</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 32px 24px;">
                            <p style="margin: 0 0 12px; color: #0f766e; font-size: 12px; font-weight: bold; letter-spacing: 1px; line-height: 20px;">ACCOUNT INVITATION</p>
                            <h1 style="margin: 0 0 24px; color: #142d45; font-size: 24px; font-weight: bold; line-height: 32px; overflow-wrap: anywhere;">Welcome, {{ $user->name }}</h1>
                            <p style="margin: 0 0 16px; font-size: 16px; line-height: 26px;">An administrator created your Employee Timekeeping &amp; Workforce Management System account.</p>
                            <p style="margin: 0 0 16px; font-size: 16px; line-height: 26px;">Use the secure link below to choose your password.</p>
                            <p style="margin: 0 0 24px; color: #475569; font-size: 14px; line-height: 22px;">This invitation link expires and can only be used once.</p>
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 24px;">
                                <tr>
                                    <td align="center" bgcolor="#0f766e" style="background-color: #0f766e; border-radius: 6px; mso-padding-alt: 14px 24px;">
                                        <a href="{{ $activationUrl }}" style="display: inline-block; padding: 14px 24px; border: 1px solid #0f766e; border-radius: 6px; color: #ffffff; font-size: 16px; font-weight: bold; line-height: 24px; text-align: center; text-decoration: none;">Set up my account</a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin: 0 0 24px; font-size: 16px; line-height: 26px;">If you were not expecting this invitation, contact your administrator.</p>
                            <p style="margin: 0; font-size: 16px; line-height: 26px;">Thanks,<br><strong style="color: #142d45;">Workforce</strong></p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 24px; border-top: 1px solid #e2e8f0;">
                            <p style="margin: 0 0 8px; color: #475569; font-size: 13px; line-height: 21px;">If the button does not work, copy and paste this link into your browser:</p>
                            <p style="margin: 0; font-size: 13px; line-height: 21px; word-break: break-all; overflow-wrap: anywhere;">
                                <a href="{{ $activationUrl }}" style="color: #0f766e; text-decoration: underline; word-break: break-all; overflow-wrap: anywhere;">{{ $activationUrl }}</a>
                            </p>
                        </td>
                    </tr>
                </table>
                <!--[if mso]>
                </td></tr></table>
                <![endif]-->
                <p style="margin: 24px 0 0; color: #475569; font-size: 12px; line-height: 20px;">&copy; {{ now()->year }} Workforce. All rights reserved.</p>
            </td>
        </tr>
    </table>
</body>
</html>
