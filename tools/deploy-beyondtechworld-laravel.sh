#!/usr/bin/env bash
# Deploy the live Laravel site (beyondtechworld.com) on the VPS.
#
# Prevents the recurring 500 caused by root-owned cache dirs under
# storage/framework after `php artisan` is run as root.
#
# Usage (on VPS):
#   bash tools/deploy-beyondtechworld-laravel.sh
#   bash tools/deploy-beyondtechworld-laravel.sh --migrate-path=database/migrations/2026_07_15_….php
#   bash tools/deploy-beyondtechworld-laravel.sh --migrate-all
#
# From your laptop (after user requests deploy):
#   ssh myvps 'cd /var/www/beyondtechworld && git pull && bash tools/deploy-beyondtechworld-laravel.sh'
set -euo pipefail

ROOT="${ROOT:-/var/www/beyondtechworld}"
APP="$ROOT/laravel-app"
WEB_USER="${WEB_USER:-www-data}"
WEB_GROUP="${WEB_GROUP:-www-data}"
MIGRATE_PATH=""
MIGRATE_ALL=0

for arg in "$@"; do
  case "$arg" in
    --migrate-all) MIGRATE_ALL=1 ;;
    --migrate-path=*) MIGRATE_PATH="${arg#--migrate-path=}" ;;
    --help|-h)
      sed -n '2,16p' "$0"
      exit 0
      ;;
  esac
done

if [[ ! -d "$APP" ]]; then
  echo "Laravel app not found at $APP"
  exit 1
fi

cd "$ROOT"

echo "==> 1. Pull latest code"
git pull || { echo "git pull failed — fix conflicts first"; exit 1; }

cd "$APP"

# Prefer running Artisan as the PHP-FPM user so new cache files are not root-owned.
run_artisan() {
  if id -u "$WEB_USER" >/dev/null 2>&1; then
    if [[ "$(id -u)" -eq 0 ]]; then
      sudo -u "$WEB_USER" -H php artisan "$@"
      return
    fi
  fi
  php artisan "$@"
}

fix_writable_dirs() {
  echo "==> Fix writable dirs for $WEB_USER (prevents Permission denied 500s)"
  mkdir -p \
    storage/framework/{cache/data,sessions,views} \
    storage/logs \
    bootstrap/cache \
    public/logo \
    public/images/site \
    public/images/quotations/qr \
    public/uploads/applications \
    public/uploads/quotations/signatures \
    public/quotation/documents

  # Ownership: PHP-FPM must write here. Root-created cache shards cause admin 500s.
  chown -R "$WEB_USER:$WEB_GROUP" storage bootstrap/cache public/logo public/images public/uploads public/quotation 2>/dev/null || true
  find storage bootstrap/cache -type d -exec chmod 775 {} \;
  find storage bootstrap/cache -type f -exec chmod 664 {} \; 2>/dev/null || true
  chmod -R ug+rwx public/logo public/images public/uploads public/quotation 2>/dev/null || true

  # Sanity: discard root-owned leftovers if any remain outside storage (rare)
  if [[ "$(id -u)" -eq 0 ]]; then
    find storage/framework/cache -user root -exec chown "$WEB_USER:$WEB_GROUP" {} \; 2>/dev/null || true
  fi
}

echo "==> 2. Ensure storage is writable BEFORE artisan (bootstrap)"
fix_writable_dirs

if [[ "$MIGRATE_ALL" -eq 1 ]]; then
  echo "==> 3. Migrate all pending"
  run_artisan migrate --force
elif [[ -n "$MIGRATE_PATH" ]]; then
  echo "==> 3. Migrate path: $MIGRATE_PATH"
  # path is relative to laravel-app/
  run_artisan migrate --path="/$MIGRATE_PATH" --force 2>/dev/null \
    || run_artisan migrate --path="$MIGRATE_PATH" --force
else
  echo "==> 3. Skip migrate (pass --migrate-all or --migrate-path=…)"
fi

echo "==> 4. Sync application version + letterhead assets"
run_artisan app:sync-version || true
run_artisan app:sync-letterhead || true

echo "==> 5. Clear / rebuild caches"
run_artisan view:clear || true
run_artisan route:clear || true
run_artisan config:clear || true
run_artisan cache:clear || true

echo "==> 6. Re-fix writable dirs AFTER artisan (critical)"
fix_writable_dirs

echo "==> 7. Reload PHP-FPM"
if systemctl is-active --quiet php7.4-fpm 2>/dev/null; then
  systemctl reload php7.4-fpm && echo "    php7.4-fpm reloaded"
elif systemctl is-active --quiet php8.1-fpm 2>/dev/null; then
  systemctl reload php8.1-fpm && echo "    php8.1-fpm reloaded"
elif systemctl is-active --quiet php8.2-fpm 2>/dev/null; then
  systemctl reload php8.2-fpm && echo "    php8.2-fpm reloaded"
else
  echo "    php-fpm unit not found — reload skipped"
fi

echo ""
echo "Laravel deploy complete."
echo "  HEAD: $(git -C "$ROOT" rev-parse --short HEAD)"
echo "  VERSION: $(cat "$APP/VERSION" 2>/dev/null || echo '?')"
echo "  Storage owner sample:"
namei -l "$APP/storage/framework/cache/data" 2>/dev/null | tail -4 || ls -la "$APP/storage/framework/cache" | head -5
