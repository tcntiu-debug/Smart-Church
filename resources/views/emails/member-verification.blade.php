<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activate your account</title>
</head>

<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,Helvetica,sans-serif;color:#333333;">
    <div style="max-width:640px;margin:0 auto;padding:24px 12px;">

        <div style="background:#0062cc;color:#ffffff;padding:20px 24px;border-radius:8px 8px 0 0;">
            <h2 style="margin:0;font-size:20px;line-height:1.3;">Activate your account</h2>
            <p style="margin:6px 0 0;font-size:13px;opacity:.9;">
                The Covenant Nation &middot; Ikorodu
            </p>
        </div>

        <div style="background:#ffffff;padding:24px;border:1px solid #e5e7eb;border-top:0;border-radius:0 0 8px 8px;">

            <p style="margin:0 0 18px;font-size:15px;line-height:1.6;">
                Hello {{ $firstName !== '' ? $firstName : 'there' }},
                <br><br>
                An account was created for you on the TCN Ikorodu Smart Church app.
                Confirm your e-mail address to activate it.
            </p>

            <p style="margin:0 0 24px;text-align:center;">
                <a href="{{ $verifyUrl }}"
                    style="display:inline-block;background:#0062cc;color:#ffffff;text-decoration:none;font-size:15px;font-weight:bold;padding:14px 28px;border-radius:6px;">
                    Activate my account
                </a>
            </p>

            <p style="margin:0 0 12px;font-size:13px;line-height:1.6;color:#4b5563;">
                The link works for {{ $expiresInDays }} days and can only be used once.
                If the button does not open, copy this address into your browser:
                <br>
                <a href="{{ $verifyUrl }}" style="color:#0062cc;word-break:break-all;">{{ $verifyUrl }}</a>
            </p>

            <p style="margin:18px 0 0;font-size:13px;line-height:1.6;color:#6b7280;">
                If you were not expecting this e-mail, you can ignore it - the account
                stays inactive until the link above is opened. Already activated?
                <a href="{{ route('login') }}" style="color:#0062cc;">Sign in here</a>.
            </p>

            <hr style="border:0;border-top:1px solid #e5e7eb;margin:20px 0;">

            <p style="margin:0;font-size:12px;color:#9ca3af;line-height:1.6;">
                No password is in this e-mail. If you have not chosen one yet, use
                "Forgot Password?" on the login page to set it.
            </p>
        </div>
    </div>
</body>

</html>
