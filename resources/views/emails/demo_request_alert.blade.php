@php
    $request = $demoRequest;
    $times = ['morning' => 'الصباح', 'evening' => 'المساء', 'any' => 'أي وقت'];
    $rows = array_filter([
        'الاسم' => $request->full_name,
        'الجوال' => $request->phone,
        'النشاط' => $request->business_activity,
        'الإيميل' => $request->email,
        'الوقت المناسب' => $times[$request->preferred_time] ?? null,
        'المصدر' => $request->source === 'mochi' ? 'موتشي (المساعد في الموقع)' : 'فورم الموقع',
        'ملاحظات' => $request->notes,
    ], fn ($value) => filled($value));
@endphp
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>طلب عرض توضيحي جديد</title>
</head>
<body style="margin:0;padding:24px;background:#F5F7FA;font-family:Tahoma,Arial,sans-serif;color:#0A0F1C;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;">
        <tr>
            <td style="background:#25D366;padding:18px 24px;color:#0A0F1C;font-size:18px;font-weight:bold;">
                📅 طلب عرض توضيحي جديد
            </td>
        </tr>
        <tr>
            <td style="padding:20px 24px;">
                <p style="margin:0 0 16px;font-size:15px;line-height:1.7;">
                    وصل طلب جديد، تواصلوا معه على واتساب في أقرب وقت.
                </p>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;line-height:1.6;">
                    @foreach ($rows as $label => $value)
                        <tr>
                            <td style="padding:6px 0;color:#5B6576;width:120px;vertical-align:top;">{{ $label }}</td>
                            <td style="padding:6px 0;font-weight:bold;" @if ($label === 'الجوال' || $label === 'الإيميل') dir="ltr" align="right" @endif>{{ $value }}</td>
                        </tr>
                    @endforeach
                </table>
                <p style="margin:24px 0 0;">
                    @if ($whatsappUrl)
                        <a href="{{ $whatsappUrl }}" style="display:inline-block;background:#25D366;color:#0A0F1C;text-decoration:none;font-weight:bold;padding:10px 18px;border-radius:10px;margin-left:8px;">تواصل على واتساب</a>
                    @endif
                    <a href="{{ $adminUrl }}" style="display:inline-block;background:#0A0F1C;color:#ffffff;text-decoration:none;font-weight:bold;padding:10px 18px;border-radius:10px;">كل الطلبات</a>
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
