#!/usr/bin/env bash
set -euo pipefail

echo "[1/8] Pull latest code"
git pull origin main

echo "[2/7] Install PHP dependencies"
composer install --no-dev --optimize-autoloader

if command -v npm >/dev/null 2>&1; then
  echo "[3/7] Install Node dependencies"
  npm ci

  echo "[4/7] Build frontend assets"
  npm run build
else
  echo "[3/7] npm not found. Skipping frontend build."
fi

echo "[5/7] Run migrations"
php artisan migrate --force

echo "[6/8] Bootstrap and validate core data"
php artisan system:bootstrap-core-data
php artisan system:health-check --strict

echo "[7/8] Clear and rebuild caches"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "[8/8] Deployment completed"
echo "Reminder: ensure queue workers are running for default, campaign-messages, and webhook queues."
