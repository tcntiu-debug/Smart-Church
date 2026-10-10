<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Birthday reminder</title>
</head>

<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,Helvetica,sans-serif;color:#333333;">
    <div style="max-width:640px;margin:0 auto;padding:24px 12px;">

        <div style="background:#0062cc;color:#ffffff;padding:20px 24px;border-radius:8px 8px 0 0;">
            <h2 style="margin:0;font-size:20px;line-height:1.3;">🎂 Birthday Reminder</h2>
            <p style="margin:6px 0 0;font-size:13px;opacity:.9;">
                {{ $campusName }} Campus &middot; Welcome Center Department
            </p>
        </div>

        <div style="background:#ffffff;padding:24px;border:1px solid #e5e7eb;border-top:0;border-radius:0 0 8px 8px;">

            <p style="margin:0 0 18px;font-size:15px;line-height:1.6;">
                Hello Welcome Center team,
                <br><br>
                @if ($daysBefore <= 0)
                    <strong>{{ count($birthdays) }}</strong>
                    {{ count($birthdays) === 1 ? 'member is' : 'members are' }} celebrating
                    {{ count($birthdays) === 1 ? 'a birthday' : 'birthdays' }}
                    <strong>today</strong> ({{ date('l, d M Y', strtotime($birthdayDate)) }}).
                @elseif ($daysBefore === 1)
                    <strong>{{ count($birthdays) }}</strong>
                    {{ count($birthdays) === 1 ? 'member has' : 'members have' }} a birthday
                    <strong>tomorrow</strong> ({{ date('l, d M Y', strtotime($birthdayDate)) }}).
                @else
                    <strong>{{ count($birthdays) }}</strong>
                    {{ count($birthdays) === 1 ? 'member has' : 'members have' }} a birthday in
                    <strong>{{ $daysBefore }} days</strong> ({{ date('l, d M Y', strtotime($birthdayDate)) }}).
                @endif
                Please reach out and make it special.
            </p>

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                <thead>
                    <tr style="background:#f4f6f9;">
                        <th align="left" style="padding:10px 12px;font-size:12px;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #e5e7eb;">Member</th>
                        <th align="left" style="padding:10px 12px;font-size:12px;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #e5e7eb;">Department</th>
                        <th align="left" style="padding:10px 12px;font-size:12px;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #e5e7eb;">Phone</th>
                        <th align="left" style="padding:10px 12px;font-size:12px;text-transform:uppercase;color:#6b7280;border-bottom:1px solid #e5e7eb;">Date</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($birthdays as $birthday)
                        <tr>
                            <td style="padding:12px;border-bottom:1px solid #f1f3f5;font-size:14px;">
                                <strong>{{ $birthday['name'] }}</strong>
                                @if (!empty($birthday['role']))
                                    <br><span style="color:#6b7280;font-size:12px;">{{ $birthday['role'] }}</span>
                                @endif
                            </td>
                            <td style="padding:12px;border-bottom:1px solid #f1f3f5;font-size:13px;color:#4b5563;">
                                {{ !empty($birthday['departments']) ? implode(', ', $birthday['departments']) : '—' }}
                            </td>
                            <td style="padding:12px;border-bottom:1px solid #f1f3f5;font-size:13px;color:#4b5563;">
                                {{ $birthday['phone'] ?: '—' }}
                            </td>
                            <td style="padding:12px;border-bottom:1px solid #f1f3f5;font-size:13px;color:#4b5563;white-space:nowrap;">
                                {{ $birthday['birthday_label'] }}
                            </td>
                        </tr>
                        @if (!empty($birthday['whatsapp_url']))
                            <tr>
                                <td colspan="4" style="padding:0 12px 12px;border-bottom:1px solid #f1f3f5;">
                                    <a href="{{ $birthday['whatsapp_url'] }}"
                                       style="display:inline-block;background:#25d366;color:#ffffff;text-decoration:none;padding:7px 14px;border-radius:20px;font-size:12px;">
                                        Send {{ $birthday['name'] }} a WhatsApp birthday wish
                                    </a>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>

            <p style="margin:22px 0 0;font-size:14px;line-height:1.6;">
                You can also see every birthday on the
                <a href="{{ url('/birthdays') }}" style="color:#0062cc;font-weight:bold;">Birthdays page</a>.
            </p>
        </div>

        <p style="margin:16px 4px;font-size:11px;color:#9ca3af;line-height:1.6;">
            This reminder is sent automatically by Smart-Church to the Welcome Center admins
            on the {{ ucfirst(config('birthday.timezone', 'Africa/Lagos')) }} schedule.
        </p>
    </div>
</body>

</html>
