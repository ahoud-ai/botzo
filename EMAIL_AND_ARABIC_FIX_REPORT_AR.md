# تقرير إصلاح اللغة العربية وتجهيز إرسال البريد

تاريخ التقرير: 2026-05-24

## الخلاصة

- تم إصلاح مشكلة تغيير اللغة للمستخدمين المسجلين.
- تم التحقق من حساب `ahmed@gmail.com`: اللغة أصبحت `ar`.
- لوحة التحكم تظهر الآن بالعربي واتجاه الصفحة `rtl`.
- إرسال رسائل التفعيل للبريد الحقيقي غير مفعل حاليًا لأن `.env` مضبوط على `MAIL_MAILER=log`.

## إصلاح اللغة العربية

المشكلة كانت في مسار:

```text
/language/{locale}
```

كان يغير `session('locale')` للزائر فقط، لكن المستخدم المسجل يعتمد على `users.language` داخل `Localization` middleware. لذلك عند تغيير اللغة لمستخدم مسجل، كانت اللغة ترجع من بيانات المستخدم القديمة.

تم تعديل:

```text
app/Http/Controllers/FrontendController.php
```

ليعمل الآتي:

- يغير `session('locale')`.
- يحدث `users.language` للمستخدم المسجل.
- يحافظ على نفس السلوك للزائر.

## نتيجة التحقق

حساب الاختبار:

```text
Email: ahmed@gmail.com
Password: Ahmed123
Language: ar
```

التحقق من المتصفح أظهر:

```text
html lang = ar
direction = rtl
Dashboard text = عربي
```

## اختبارات اللغة

تمت إضافة اختبار:

```text
tests/Feature/LocaleSwitchingTest.php
```

ويغطي:

- تغيير لغة المستخدم المسجل وحفظها في profile.
- تغيير لغة الزائر وحفظها في session.

نتيجة التشغيل:

```text
3 passed
23 assertions
```

الأمر:

```bash
php artisan test tests/Feature/LocaleSwitchingTest.php tests/Feature/SignupActivationPlanSelectionFlowTest.php
```

## حالة البريد الإلكتروني الآن

الإعداد الحالي في `.env`:

```env
MAIL_MAILER=log
```

هذا يعني أن رسائل التفعيل لا تصل إلى Gmail، بل تكتب داخل:

```text
storage/logs/laravel.log
```

إعدادات قاعدة البيانات الحالية:

```text
verify_email = 1
smtp_email_active = 0
```

`verify_email=1` يجعل النظام يطلب تفعيل البريد.  
لكن `MAIL_MAILER=log` يمنع الإرسال الحقيقي ويكتبه في اللوج فقط.

## المطلوب لتفعيل الإرسال الحقيقي

لا يمكن استخدام باسورد حساب التطبيق `Ahmed123` ككلمة مرور SMTP. لو المرسل Gmail، يجب إنشاء Google App Password من حساب Gmail المرسل.

بعد توفير App Password أو بيانات SMTP حقيقية، ضع القيم في `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-sender@gmail.com
MAIL_PASSWORD=your-google-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-sender@gmail.com
MAIL_FROM_NAME=Botzo
```

ثم شغل:

```bash
php artisan config:clear
php artisan cache:clear
```

ولتفعيل إرسال قوالب الإيميل الأخرى داخل النظام:

```text
smtp_email_active = 1
```

أما رسائل تفعيل البريد نفسها فهي تستخدم Laravel Mail مباشرة، وتحتاج فقط أن `MAIL_MAILER` يكون SMTP صحيحًا.

## القرار

اللغة العربية أصبحت تعمل بشكل سليم للمستخدم المسجل.

الإيميل الحقيقي غير مفعل بعد لأن بيانات SMTP الحقيقية غير متوفرة. بمجرد توفير:

```text
MAIL_USERNAME
MAIL_PASSWORD / App Password
MAIL_FROM_ADDRESS
```

يمكن تفعيل الإرسال الحقيقي فورًا.
