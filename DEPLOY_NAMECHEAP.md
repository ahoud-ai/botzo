# Deploy Botzo to Namecheap

This project is already connected to GitHub at:

```text
https://github.com/ahoud-ai/botzo.git
```

The Namecheap screenshots show two names:

- Hosting subscription: `botzo.site`
- Domain details page: `botzo.net`

Use the domain you want live in every `APP_URL` and DNS step below.

## 1. Namecheap DNS

In Namecheap, keep the domain nameservers set to Namecheap Web Hosting DNS:

```text
dns1.namecheaphosting.com
dns2.namecheaphosting.com
```

The domain details screenshot already shows `Namecheap Web Hosting DNS`, so DNS looks close. If the hosting page still warns that nameservers are not pointed, wait for propagation or make the hosting subscription's primary/addon domain match the domain you want live.

## 2. cPanel setup

Open cPanel from Namecheap Hosting List.

Set PHP version to `8.2` and enable these extensions if available:

```text
bcmath, curl, dom, fileinfo, gd, mbstring, pdo_mysql, tokenizer, xml, zip
```

Create a MySQL database, user, and password from cPanel. Save:

```text
DB_DATABASE
DB_USERNAME
DB_PASSWORD
DB_HOST
```

Usually `DB_HOST` is `localhost` on Namecheap shared hosting.

Import the production SQL dump through phpMyAdmin before first boot.

## 3. Server `.env`

Create `.env` in the deployed app root on the server. Do not commit it to GitHub.

```env
APP_NAME=Botzo
APP_ENV=production
APP_KEY=base64:GENERATE_THIS_ON_SERVER
APP_DEBUG=false
APP_URL=https://botzo.net

DB_CONNECTION=mysql
DB_HOST=localhost
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
MAIL_MAILER=log
```

Generate `APP_KEY` from cPanel Terminal:

```bash
php artisan key:generate --force
```

## 4. GitHub Secrets

In GitHub:

`Settings -> Secrets and variables -> Actions -> New repository secret`

Add:

```text
NAMECHEAP_FTP_SERVER
NAMECHEAP_FTP_USERNAME
NAMECHEAP_FTP_PASSWORD
NAMECHEAP_FTP_SERVER_DIR
```

Typical values:

```text
NAMECHEAP_FTP_SERVER=ftp.your-domain.com
NAMECHEAP_FTP_SERVER_DIR=/public_html/
```

For an addon domain, `NAMECHEAP_FTP_SERVER_DIR` may be a domain folder such as:

```text
/botzo.net/
```

or:

```text
/public_html/botzo.net/
```

Check the exact document root in cPanel Domains.

## 5. Deploy

Push to `main`, or run manually:

`GitHub -> Actions -> Deploy to Namecheap -> Run workflow`

The workflow builds Composer dependencies, builds Vite assets, and uploads the production files over FTP.

## 6. First-run commands

If cPanel Terminal or SSH is available, run these in the deployed app root after the first upload:

```bash
php artisan migrate --force
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

If Terminal is not available, use cPanel File Manager to confirm these folders exist and are writable:

```text
storage/
storage/framework/cache/
storage/framework/sessions/
storage/framework/views/
storage/logs/
bootstrap/cache/
```

## 7. SSL

In cPanel, run AutoSSL for the domain. The included `.htaccess` allows `.well-known` challenges and redirects normal traffic to HTTPS.
