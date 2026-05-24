# تشغيل Botzo على Namecheap cPanel مع الدومين botzo.net

## الوضع الحالي

- الاستضافة موجودة على Namecheap:
  - Plan: Stellar Plus
  - Status: Active
- الريبو الجديد:
  - `https://github.com/mahmoudhamed169/botzoo.git`
- الدومين المطلوب تشغيله:
  - `botzo.net`

## 1. ربط الدومين بالاستضافة

من Namecheap يجب التأكد أن `botzo.net` يستخدم Nameservers الخاصة بالاستضافة:

```text
dns1.namecheaphosting.com
dns2.namecheaphosting.com
```

إذا كان الدومين داخل نفس حساب Namecheap:

1. ادخل على `Domain List`.
2. افتح `Manage` بجانب `botzo.net`.
3. من `Nameservers` اختر `Namecheap Web Hosting DNS`.
4. احفظ التغيير.

قد يحتاج انتشار DNS من دقائق حتى 24 ساعة.

## 2. إضافة الدومين داخل cPanel

1. من صفحة `Hosting List` اضغط `GO TO CPANEL`.
2. افتح `Domains`.
3. اختر `Create A New Domain`.
4. أدخل:
   - Domain: `botzo.net`
   - Document Root: الأفضل أن يكون داخل مجلد المشروع `public`

الاختيار الأفضل:

```text
/home/CPANEL_USER/botzo.net/public
```

إذا cPanel لم يسمح بهذا المسار، يمكن وضع المشروع داخل مجلد الدومين مباشرة لأن المشروع يحتوي ملف `index.php` و`.htaccess` في الجذر يوجهان الطلبات إلى `public`.

## 3. رفع الكود من GitHub

من cPanel إذا كان `Git Version Control` متاحًا:

1. افتح `Git Version Control`.
2. اختر `Create`.
3. Repository URL:

```text
https://github.com/mahmoudhamed169/botzoo.git
```

4. Branch:

```text
main
```

5. Clone path:

```text
/home/CPANEL_USER/botzo.net
```

إذا كان الريبو Private، استخدم SSH key من cPanel أو اجعل الريبو Public مؤقتًا أثناء النشر.

## 4. تجهيز PHP

من cPanel:

1. افتح `Select PHP Version` أو `MultiPHP Manager`.
2. اختر PHP 8.2 للدومين `botzo.net`.
3. فعّل الامتدادات المهمة إن وجدت:
   - `pdo_mysql`
   - `mbstring`
   - `xml`
   - `curl`
   - `zip`
   - `gd`
   - `bcmath`
   - `fileinfo`
   - `openssl`

## 5. إنشاء قاعدة البيانات

من cPanel:

1. افتح `MySQL Databases`.
2. أنشئ Database جديدة.
3. أنشئ User جديد.
4. اربط الـ User بالـ Database مع `All Privileges`.
5. احتفظ بالقيم التالية:
   - Database name
   - Username
   - Password

## 6. إنشاء ملف .env على السيرفر

داخل مجلد المشروع على السيرفر أنشئ ملف `.env`.

القالب الأساسي:

```env
APP_NAME=Botzo
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://botzo.net

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=CPANEL_DATABASE_NAME
DB_USERNAME=CPANEL_DATABASE_USER
DB_PASSWORD=CPANEL_DATABASE_PASSWORD

CACHE_DRIVER=file
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
FILESYSTEM_DISK=local
BROADCAST_DRIVER=log

MAIL_MAILER=smtp
MAIL_HOST=SMTP_HOST
MAIL_PORT=587
MAIL_USERNAME=SMTP_USERNAME
MAIL_PASSWORD=SMTP_PASSWORD
MAIL_FROM_ADDRESS=no-reply@botzo.net
MAIL_FROM_NAME="${APP_NAME}"
```

مهم: لا ترفع ملف `.env` إلى GitHub.

## 7. أوامر التشغيل من Terminal داخل cPanel

إذا كان `Terminal` متاحًا:

```bash
cd ~/botzo.net
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

إذا لم يكن Node.js متاحًا على cPanel، يتم بناء الواجهة محليًا ثم رفع مجلد `public/build` للسيرفر.

## 8. SSL

من cPanel:

1. افتح `SSL/TLS Status`.
2. اختر `botzo.net`.
3. اضغط `Run AutoSSL`.

بعد تفعيل SSL يجب أن يعمل الموقع على:

```text
https://botzo.net
```

## 9. البريد وتفعيل الحساب

لكي تصل رسائل التفعيل يجب استخدام SMTP حقيقي.

اختيارات مناسبة:

- بريد من Namecheap Private Email.
- Gmail App Password.
- Resend أو Mailgun.

إذا بقي `MAIL_MAILER=log` فلن تصل أي رسالة إلى البريد، وستذهب الرسائل إلى لوج Laravel فقط.

## 10. ملاحظات تشغيل مهمة

- على Shared Hosting استخدم:

```env
QUEUE_CONNECTION=sync
```

- لا تشغل `php artisan migrate:fresh` على السيرفر لأنه يمسح البيانات.
- إذا ظهرت صفحة 500:
  - راجع `storage/logs/laravel.log`.
  - تأكد من صلاحيات `storage` و`bootstrap/cache`.
  - شغل `php artisan optimize:clear`.

## 11. ما أحتاجه منك لإكمال النشر معك خطوة بخطوة

- Screenshot من داخل cPanel بعد الضغط على `GO TO CPANEL`.
- هل الريبو Private أم Public؟
- هل Terminal متاح في cPanel؟
- هل تريد استخدام SMTP من Namecheap أم Gmail/Resend؟
