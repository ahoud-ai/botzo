@php
    $companyName = $companyName ?? config('app.name', 'Botzo');
    $isVerificationEmail = isset($verificationCode) && $verificationCode !== '';
    $brandGreen = '#25D366';
    $brandCyan = '#00E5FF';
    $brandBlue = '#1877F2';
    $brandPurple = '#7C3AED';
    $brandInk = '#0A0F1C';
    $brandSoft = '#F5F7FA';
    $arabicTitle = "\u{062A}\u{0641}\u{0639}\u{064A}\u{0644} \u{062D}\u{0633}\u{0627}\u{0628}\u{0643} \u{0641}\u{064A}";
    $arabicGreeting = "\u{0645}\u{0631}\u{062D}\u{0628}\u{0627}";
    $arabicBody = "\u{0627}\u{0633}\u{062A}\u{062E}\u{062F}\u{0645} \u{0631}\u{0645}\u{0632} \u{0627}\u{0644}\u{062A}\u{0641}\u{0639}\u{064A}\u{0644} \u{0627}\u{0644}\u{062A}\u{0627}\u{0644}\u{064A} \u{0644}\u{0625}\u{0643}\u{0645}\u{0627}\u{0644} \u{0625}\u{0646}\u{0634}\u{0627}\u{0621} \u{062D}\u{0633}\u{0627}\u{0628}\u{0643}. \u{0644}\u{0627} \u{062A}\u{0634}\u{0627}\u{0631}\u{0643} \u{0647}\u{0630}\u{0627} \u{0627}\u{0644}\u{0631}\u{0645}\u{0632} \u{0645}\u{0639} \u{0623}\u{064A} \u{0634}\u{062E}\u{0635}.";
    $arabicCodeLabel = "\u{0631}\u{0645}\u{0632} \u{0627}\u{0644}\u{062A}\u{0641}\u{0639}\u{064A}\u{0644}";
    $arabicExpires = "\u{064A}\u{0646}\u{062A}\u{0647}\u{064A} \u{062E}\u{0644}\u{0627}\u{0644} 15 \u{062F}\u{0642}\u{064A}\u{0642}\u{0629}";
    $arabicFooter = "\u{062A}\u{0645} \u{0625}\u{0631}\u{0633}\u{0627}\u{0644} \u{0647}\u{0630}\u{0647} \u{0627}\u{0644}\u{0631}\u{0633}\u{0627}\u{0644}\u{0629} \u{0628}\u{0648}\u{0627}\u{0633}\u{0637}\u{0629}";
@endphp
<!doctype html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $companyName }}</title>
</head>
<body style="margin:0;padding:0;background:{{ $brandSoft }};font-family:'Segoe UI',Tahoma,Arial,Helvetica,sans-serif;color:{{ $brandInk }};">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0;padding:34px 12px;background:{{ $brandSoft }};">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border:1px solid #E5EBF3;border-radius:18px;overflow:hidden;box-shadow:0 18px 50px rgba(10,15,28,0.08);">
                    <tr>
                        <td style="height:6px;background:linear-gradient(90deg, {{ $brandGreen }} 0%, {{ $brandCyan }} 34%, {{ $brandBlue }} 68%, {{ $brandPurple }} 100%);font-size:0;line-height:0;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td style="padding:26px 30px 20px;background:#ffffff;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td align="left" style="vertical-align:middle;">
                                        <table role="presentation" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td style="vertical-align:middle;padding-right:12px;">
                                                    @if(! empty($logoUrl))
                                                        <img src="{{ $logoUrl }}" alt="{{ $companyName }}" width="46" height="46" style="display:block;width:46px;height:46px;border:0;border-radius:12px;">
                                                    @else
                                                        <div style="width:46px;height:46px;border-radius:12px;background:linear-gradient(135deg, {{ $brandGreen }}, {{ $brandCyan }} 45%, {{ $brandPurple }});"></div>
                                                    @endif
                                                </td>
                                                <td style="vertical-align:middle;">
                                                    <div style="font-size:28px;font-weight:800;letter-spacing:-0.4px;color:{{ $brandInk }};line-height:1;">botzo</div>
                                                    <div style="font-size:11px;color:#64748B;line-height:1.7;">
                                                        <span style="color:{{ $brandGreen }};">WhatsApp</span> &amp; <span style="color:{{ $brandBlue }};">Meta</span> Solutions
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                    <td align="right" style="vertical-align:middle;">
                                        <div style="display:inline-block;border:1px solid #DDE6F0;border-radius:999px;padding:8px 13px;font-size:12px;color:#334155;background:#ffffff;">Account activation</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    @if($isVerificationEmail)
                        <tr>
                            <td style="padding:8px 30px 0;background:#ffffff;">
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:linear-gradient(135deg, rgba(37,211,102,0.10), rgba(0,229,255,0.08) 40%, rgba(124,58,237,0.10));border:1px solid #E5EEF8;border-radius:16px;">
                                    <tr>
                                        <td style="padding:24px;">
                                            <div dir="rtl" style="text-align:right;margin-bottom:22px;">
                                                <div style="font-size:24px;line-height:1.45;font-weight:800;color:{{ $brandInk }};margin-bottom:8px;">{{ $arabicTitle }} {{ $companyName }}</div>
                                                <div style="font-size:15px;line-height:1.9;color:#475569;">{{ $arabicGreeting }} {{ $firstName ?? '' }}، {{ $arabicBody }}</div>
                                            </div>
                                            <div dir="ltr" style="text-align:left;">
                                                <div style="font-size:22px;line-height:1.35;font-weight:800;color:{{ $brandInk }};margin-bottom:8px;">Activate your {{ $companyName }} account</div>
                                                <div style="font-size:15px;line-height:1.75;color:#475569;">Hi {{ $firstName ?? '' }}, use the verification code below to finish creating your account. Do not share this code with anyone.</div>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:24px 30px 22px;background:#ffffff;">
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#ffffff;border:1px solid #DFE8F3;border-radius:16px;">
                                    <tr>
                                        <td align="center" style="padding:24px 18px 22px;">
                                            <div style="font-size:12px;line-height:1.5;color:#64748B;text-transform:uppercase;letter-spacing:1px;margin-bottom:12px;">Verification Code / {{ $arabicCodeLabel }}</div>
                                            <div style="display:inline-block;padding:17px 26px;border-radius:14px;background:#F8FAFC;border:1px solid #E2E8F0;">
                                                <span style="font-size:40px;line-height:1;font-weight:900;letter-spacing:9px;color:{{ $brandInk }};font-family:Arial,Helvetica,sans-serif;">{{ $verificationCode }}</span>
                                            </div>
                                            <div style="font-size:13px;line-height:1.8;color:#64748B;margin-top:14px;">Expires in 15 minutes / {{ $arabicExpires }}</div>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        @if(! empty($verificationUrl))
                            <tr>
                                <td align="center" style="padding:0 30px 30px;background:#ffffff;">
                                    <a href="{{ $verificationUrl }}" style="display:inline-block;background:linear-gradient(90deg, {{ $brandGreen }}, {{ $brandBlue }} 58%, {{ $brandPurple }});color:#ffffff;text-decoration:none;border-radius:999px;padding:14px 26px;font-size:14px;font-weight:800;">Open activation page</a>
                                </td>
                            </tr>
                        @endif
                    @else
                        <tr>
                            <td style="padding:26px 30px 30px;font-size:15px;line-height:1.7;color:#334155;background:#ffffff;">
                                {!! $body !!}
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td style="background:#FAFBFD;border-top:1px solid #E5EBF3;padding:18px 30px;text-align:center;color:#64748B;font-size:12px;line-height:1.8;">
                            <div style="height:3px;width:120px;background:linear-gradient(90deg, {{ $brandGreen }}, {{ $brandCyan }}, {{ $brandBlue }}, {{ $brandPurple }});border-radius:999px;margin:0 auto 12px;"></div>
                            <div>This message was sent by {{ $companyName }}.</div>
                            <div dir="rtl">{{ $arabicFooter }} {{ $companyName }}.</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
