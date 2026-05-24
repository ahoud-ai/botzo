# Botzo Free Deployment Notes

## Project Stack

- Backend: Laravel 12 on PHP 8.2.
- Frontend: Vue 3 + Inertia + Vite.
- Database: MySQL/MariaDB.
- Build tools: Composer, Node.js 20, npm, Vite.
- Important integrations: WhatsApp Cloud API, Moyasar, OpenAI, Pusher, SMTP.

## Database Files

Local database dump:

- `E:\Projects\New folder\database-botzo_sa.sql`
- `E:\Projects\New folder\database-botzo_sa.sql.gz`

The dump is MariaDB/MySQL and includes the `migrations` table, so after importing it the app can still run `php artisan migrate --force` safely for missing migrations.

## Best Free Hosting Paths

### Option A: ByetHost or similar free PHP/MySQL hosting

Best when you need a truly free demo with MySQL and no credit card.

Tradeoffs:

- The host must allow PHP 8.2. PHP 8.5 is too new for the locked dependencies, and PHP 8.3 may also fail with the current lock file.
- No proper queue worker.
- No SSH on many free plans.
- You may need to build locally and upload files by FTP/File Manager.
- Local filesystem uploads are fragile on free shared hosting.

High-level steps:

1. Create a free hosting account.
2. Create a MySQL database.
3. Import `database-botzo_sa.sql.gz` with phpMyAdmin.
4. Upload the Laravel project files.
5. Set the domain document root to `public` if the host allows it.
6. If it does not allow changing document root, use the root `index.php` that already forwards to `public/index.php`.
7. Create `.env` on the server with production values.
8. Run or upload dependencies:
   - `composer install --no-dev --optimize-autoloader`
   - `npm install`
   - `npm run build`
9. Clear/cache Laravel config if SSH is available:
   - `php artisan optimize:clear`
   - `php artisan config:cache`
   - `php artisan route:cache`
   - `php artisan view:cache`

### Option B: Railway

Best when you want the easiest GitHub deployment and are okay with trial/usage limits.

The repo already has `nixpacks.toml`, so Railway can build it.
It pins PHP 8.2, which matches the current `composer.lock`.

High-level steps:

1. Create a Railway project from the GitHub repo.
2. Add a MySQL database service.
3. Import the SQL dump into the Railway MySQL database.
4. Add environment variables to the app service.
5. Generate a public domain from Railway Networking.
6. Deploy.

## Required Environment Variables

Minimum for first boot:

```env
APP_NAME=Botzo
APP_ENV=production
APP_KEY=base64:GENERATE_THIS
APP_DEBUG=false
APP_URL=https://YOUR_DOMAIN

DB_CONNECTION=mysql
DB_HOST=YOUR_DB_HOST
DB_PORT=3306
DB_DATABASE=YOUR_DB_NAME
DB_USERNAME=YOUR_DB_USER
DB_PASSWORD=YOUR_DB_PASSWORD

CACHE_DRIVER=file
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
FILESYSTEM_DISK=local
BROADCAST_DRIVER=log
LOG_CHANNEL=stack
```

Optional but needed for full production features:

```env
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME="${APP_NAME}"

PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_APP_CLUSTER=mt1

GRAPH_API_VERSION=v21.0
CAMPAIGN_DISPATCH_TOKEN=
UTILITY_API_TOKEN=
```

WhatsApp, OpenAI, and Moyasar values may also be stored in the database settings/admin screens, depending on the feature.

## Important Notes

- Do not commit `.env`.
- Do not run fresh migrations against an imported production dump.
- Import the database first, then let Laravel run normal pending migrations only.
- On free hosting, use `QUEUE_CONNECTION=sync` unless you have a real worker process.
- For WhatsApp webhooks, the app must have a public HTTPS URL.
- For media uploads in real production, S3-compatible storage is safer than local storage.
