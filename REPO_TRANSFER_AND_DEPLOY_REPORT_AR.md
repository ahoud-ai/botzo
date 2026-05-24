# تقرير نقل الريبو وتجهيز النشر

## الحالة الحالية

- الريبو الحالي مربوط على:
  - `https://github.com/ahoud-ai/botzo.git`
- الفرع الحالي:
  - `main`
- لا توجد صلاحية Admin على الريبو الحالي حسب كلامك، لذلك الأفضل رفع نسخة إلى ريبو جديد على حسابك.

## ما تم تجهيزه قبل الرفع

- تم التأكد أن الملفات الحساسة غير مرفوعة:
  - `.env`
  - `vendor/`
  - `node_modules/`
  - `storage/logs/`
- تم إضافة استبعاد لملفات التشغيل والاختبار المحلية:
  - `test-artifacts/`
  - `serve.log`
- تم إصلاح توافق إعدادات قاعدة البيانات بين PHP 8.2 و PHP 8.5:
  - يتم استخدام `Pdo\Mysql::ATTR_SSL_CA` إذا كان متاحًا.
  - ويتم الرجوع إلى `PDO::MYSQL_ATTR_SSL_CA` على الإصدارات الأقدم.
  - السبب: المشروع محدد PHP 8.2، بينما جهاز التطوير يعمل على PHP 8.5.

## الملفات المقترح رفعها في أول Commit

- إصلاح اللغة العربية:
  - `app/Http/Controllers/FrontendController.php`
- اختبار تغيير اللغة:
  - `tests/Feature/LocaleSwitchingTest.php`
- اختبار فلو التسجيل والتفعيل واختيار الباقة:
  - `tests/Feature/SignupActivationPlanSelectionFlowTest.php`
- تقارير التوثيق والاختبار:
  - `PROJECT_DOCUMENTATION_AR.md`
  - `ONBOARDING_FLOW_TEST_REPORT_AR.md`
  - `EMAIL_AND_ARABIC_FIX_REPORT_AR.md`
  - `REPO_TRANSFER_AND_DEPLOY_REPORT_AR.md`
- تجهيزات Git:
  - `.gitignore`
- توافق PHP 8.2/8.5:
  - `config/database.php`

## ما لا يجب رفعه

- ملف `.env` لأنه يحتوي إعدادات وأسرار محلية.
- مجلد `test-artifacts/` لأنه يحتوي صور ونتائج تشغيل محلية.
- مجلدات الاعتماديات:
  - `vendor/`
  - `node_modules/`
- ملفات اللوج:
  - `storage/logs/*`
  - `serve.log`

## خطوات رفع الريبو الجديد

1. أنشئ ريبو جديد على GitHub من حسابك.
2. لا تضف README أو `.gitignore` من GitHub إذا سألك، لأن المشروع يحتوي هذه الملفات بالفعل.
3. أرسل لي رابط الريبو الجديد، مثال:
   - HTTPS: `https://github.com/YOUR_USER/YOUR_REPO.git`
   - أو SSH: `git@github.com:YOUR_USER/YOUR_REPO.git`
4. سأضيفه كـ remote جديد باسم `personal`.
5. سأعمل Commit للتغييرات الجاهزة.
6. سأرفع الفرع `main` على الريبو الجديد.

## خطة تشغيل السيرفر

المشروع Laravel 12 + Vue/Inertia + Vite ويحتاج:

- PHP 8.2 أو أحدث متوافق
- Composer
- Node.js 20
- MySQL أو MariaDB
- Web server مثل Nginx/Apache، أو منصة تدعم Laravel مثل Railway

### متغيرات البيئة المطلوبة

يجب إنشاء `.env` على السيرفر بقيم حقيقية، وأهمها:

```env
APP_NAME=Botzo
APP_ENV=production
APP_KEY=base64:GENERATE_ON_SERVER
APP_DEBUG=false
APP_URL=https://YOUR_DOMAIN

DB_CONNECTION=mysql
DB_HOST=YOUR_DB_HOST
DB_PORT=3306
DB_DATABASE=YOUR_DB_NAME
DB_USERNAME=YOUR_DB_USER
DB_PASSWORD=YOUR_DB_PASSWORD

MAIL_MAILER=smtp
MAIL_HOST=YOUR_SMTP_HOST
MAIL_PORT=587
MAIL_USERNAME=YOUR_SMTP_USER
MAIL_PASSWORD=YOUR_SMTP_PASSWORD
MAIL_FROM_ADDRESS=no-reply@YOUR_DOMAIN
MAIL_FROM_NAME="${APP_NAME}"
```

### أوامر التشغيل على السيرفر

```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build
php artisan key:generate --force
php artisan migrate --force
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## بيانات مطلوبة منك للخطوة التالية

- رابط الريبو الجديد على حسابك.
- نوع السيرفر الذي سننشر عليه:
  - VPS Ubuntu
  - cPanel
  - Railway
  - Render
  - أي استضافة أخرى
- هل يوجد دومين جاهز أم سنستخدم رابط مؤقت؟
- بيانات قاعدة البيانات أو هل تريد إنشاء قاعدة جديدة على السيرفر؟
- بيانات SMTP حقيقية حتى تصل رسائل تفعيل الحساب إلى البريد.

## ملاحظة مهمة عن البريد

مشكلة عدم وصول رسالة التفعيل كانت بسبب أن البيئة المحلية تستخدم غالبًا `MAIL_MAILER=log`، وهذا لا يرسل إلى Gmail بل يكتب الرسائل داخل لوج Laravel. على السيرفر يجب ضبط SMTP حقيقي مثل Gmail App Password أو Resend أو Mailgun أو SMTP خاص بالدومين.
