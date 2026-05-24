# أوامر مزامنة GitHub مع النسخة العاملة على botzo.net

هذه الأوامر هدفها توحيد الكود بين GitHub والمسار الذي يعمل عليه الدومين.

## المسارات الحالية على السيرفر

كود GitHub موجود هنا:

```bash
/home/botzkprp/repositories/botzoo
```

الموقع الذي يخدم `botzo.net` يعمل من هنا:

```bash
/home/botzkprp/botzo.net
```

## أمر النشر الآمن

نفذ الأوامر التالية من cPanel Terminal:

```bash
cd /home/botzkprp/repositories/botzoo
git pull
php scripts/ensure-prebuilt-build.php --force

rsync -a \
  --exclude='.git/' \
  --exclude='.env' \
  --exclude='storage/' \
  --exclude='vendor/' \
  --exclude='node_modules/' \
  --exclude='bootstrap/cache/' \
  --exclude='public/storage' \
  /home/botzkprp/repositories/botzoo/ \
  /home/botzkprp/botzo.net/

cd /home/botzkprp/botzo.net
composer install --no-dev --optimize-autoloader
php scripts/ensure-prebuilt-build.php --force
mkdir -p storage/logs storage/framework/cache/data storage/framework/sessions storage/framework/views storage/app/public bootstrap/cache
touch storage/installed
chmod -R 775 storage bootstrap/cache
php artisan optimize:clear
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## فحص بعد النشر

```bash
curl -k -I https://botzo.net/
curl -k -I https://botzo.net/login
curl -k -I https://botzo.net/register
curl -k https://botzo.net/current-locale
curl -k https://botzo.net/health/ready
```

المتوقع:

```text
/             200
/login        200
/register     200
/current-locale يرجع ar
/health/ready يرجع ready
```

## ملاحظات مهمة

- لا تنسخ ملف `.env` من GitHub إلى السيرفر.
- لا تحذف مجلد `storage` أو `vendor` من النسخة العاملة.
- لا تستخدم `rsync --delete` في هذه المرحلة.
- إذا رجع الموقع `503` تأكد من وجود:

```bash
storage/installed
```

- إذا رجعت ملفات PHP بخطأ `403` تأكد أن `public/.htaccess` لا يحتوي على:

```apache
AddHandler application/x-httpd-ea-php82 .php .php8 .phtml
```
