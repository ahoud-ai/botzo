<?php
    $currentYear = date('Y');
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="refresh" content="60">
<meta name="robots" content="noindex, follow">
<title>Botzo — صيانة</title>
<link rel="icon" href="{{ $faviconUrl }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{--accent:#22C55E}
*{box-sizing:border-box}
body{margin:0;background:#080C10}
a{color:#4ADE80}a:hover{color:#86EFAC}
@keyframes bz-pulse{0%{box-shadow:0 0 0 0 rgba(34,197,94,.55)}70%{box-shadow:0 0 0 8px rgba(34,197,94,0)}100%{box-shadow:0 0 0 0 rgba(34,197,94,0)}}
@keyframes bz-slide{0%{transform:translateX(120%)}100%{transform:translateX(-320%)}}
@keyframes bz-spin{to{transform:rotate(360deg)}}
.bz-dot{animation:bz-pulse 1.8s ease-out infinite}
.bz-bar{animation:bz-slide 1.6s cubic-bezier(.45,.05,.35,1) infinite}
.bz-gear{animation:bz-spin 9s linear infinite;transform-origin:50% 50%}
@media (max-width:640px){.bz-h1{font-size:40px !important}.bz-meta{grid-template-columns:minmax(0,1fr) !important}.bz-actions{flex-direction:column !important;align-items:stretch !important}}
@media (prefers-reduced-motion:reduce){.bz-dot,.bz-bar,.bz-gear{animation:none}}
</style>
</head>
<body>
<div dir="rtl" style="min-height: 100vh; display: flex; flex-direction: column; font-family: 'IBM Plex Sans Arabic', system-ui, sans-serif; color: #E8EEF2; background-color: #080C10; background-image: radial-gradient(rgba(148,163,184,0.09) 1px, transparent 1px); background-size: 28px 28px; padding: 0 24px">

<header style="width: 100%; max-width: 1120px; margin: 0 auto; padding: 28px 0; display: flex; justify-content: space-between; align-items: center; gap: 16px">
<div style="display: flex; align-items: center; gap: 8px">
<img src="{{ url('/images/nav/nav-icon-dark.svg') }}" alt="" style="width: 30.68px; height: 29.9px; display: block">
<span style="font-size: 20px; font-weight: 700; letter-spacing: 0.2px" dir="ltr">Botzo</span>
</div>
<div style="display: inline-flex; align-items: center; gap: 10px; padding: 8px 14px; border: 1px solid #1F2B35; border-radius: 999px; background: #0E151C; font-size: 14px; color: #B6C2CC">
<span class="bz-dot" style="width: 8px; height: 8px; border-radius: 50%; background: var(--accent); display: inline-block"></span>
<span>الصيانة جارية الآن</span>
</div>
</header>

<main style="flex-grow: 1; display: flex; align-items: center; justify-content: center; padding: 40px 0 64px">
<div style="width: 100%; max-width: 640px; display: flex; flex-direction: column; align-items: center; text-align: center; gap: 28px">

<div style="position: relative; width: 104px; height: 104px; border-radius: 28px; background: #0E151C; border: 1px solid #1F2B35; display: flex; align-items: center; justify-content: center">
<svg class="bz-gear" width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
<div style="position: absolute; bottom: -10px; left: -10px; width: 40px; height: 40px; border-radius: 12px; background: var(--accent); display: flex; align-items: center; justify-content: center; border: 4px solid #080C10">
<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#04140A" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>
</div>
</div>

<div style="display: flex; flex-direction: column; gap: 14px; align-items: center">
<h1 class="bz-h1" style="margin: 0; font-size: 56px; line-height: 1.2; font-weight: 700; letter-spacing: -0.5px">هنرجع قريبًا</h1>
<p style="margin: 0; font-size: 18px; line-height: 1.9; color: #A3B1BC; max-width: 520px">نعمل حاليًا على صيانة مجدولة لتحسين أداء منصة <span dir="ltr" style="color: #E8EEF2; font-weight: 600">Botzo</span> وإضافة تحسينات جديدة. شكرًا لصبرك، سنعود خلال وقت قصير.</p>
</div>

<div style="width: 100%; max-width: 420px; height: 4px; border-radius: 999px; background: #16212A; overflow: hidden; display: flex; justify-content: flex-end">
<div class="bz-bar" style="width: 35%; height: 100%; border-radius: 999px; background: var(--accent)"></div>
</div>

<div class="bz-meta" style="width: 100%; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px">
@if ($maintenanceEta)
<div style="padding: 18px 20px; border: 1px solid #1F2B35; border-radius: 16px; background: #0E151C; display: flex; flex-direction: column; gap: 6px; text-align: right">
<span style="font-size: 13px; color: #8D9BA7">الوقت المتوقع للعودة</span>
<span style="font-size: 18px; font-weight: 600">{{ $maintenanceEta }}</span>
</div>
@endif
<div style="padding: 18px 20px; border: 1px solid #1F2B35; border-radius: 16px; background: #0E151C; display: flex; flex-direction: column; gap: 6px; text-align: right">
<span style="font-size: 13px; color: #8D9BA7">بياناتك</span>
<span style="font-size: 18px; font-weight: 600">آمنة ومحفوظة بالكامل</span>
</div>
</div>

<div class="bz-actions" style="display: flex; gap: 12px; align-items: center; justify-content: center">
<button type="button" onclick="location.reload()" style="font-family: inherit; font-size: 16px; font-weight: 600; min-height: 48px; padding: 0 24px; border-radius: 12px; border: none; background: var(--accent); color: #04140A; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px">
<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-3-6.7L21 8"></path><path d="M21 3v5h-5"></path></svg>
تحديث الصفحة
</button>
<a href="mailto:{{ $supportEmail }}" style="font-size: 16px; font-weight: 500; min-height: 48px; padding: 0 24px; border-radius: 12px; border: 1px solid #2A3844; background: transparent; color: #E8EEF2; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 8px">
<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4h16v16H4z"></path><path d="m4 6 8 7 8-7"></path></svg>
تواصل مع الدعم
</a>
</div>

</div>
</main>

<footer style="width: 100%; max-width: 1120px; margin: 0 auto; padding: 24px 0 32px; border-top: 1px solid #141E26; display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; font-size: 14px; color: #8D9BA7">
<span>© {{ $currentYear }} <span dir="ltr">Botzo</span>. جميع الحقوق محفوظة.</span>
<span>تابع آخر التحديثات عبر <a href="{{ $statusLink }}">حساب التواصل</a></span>
</footer>

</div>
</body>
</html>
