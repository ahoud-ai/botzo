#!/usr/bin/env bash
set -euo pipefail

SKIP_MIGRATE="${SKIP_MIGRATE:-0}"

echo "==> Clearing cached bootstrap artifacts"
php artisan optimize:clear --env=testing

echo "==> Running test safety guard"
php artisan system:test-safety-check --env=testing

if [[ "$SKIP_MIGRATE" != "1" ]]; then
  echo "==> Preparing isolated testing database"
  php artisan system:prepare-testing-database --env=testing --force
fi

echo "==> Running test suite"
php artisan test --env=testing

echo "==> Safe test run completed"
