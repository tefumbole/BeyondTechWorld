#!/bin/bash
# Safe deploy for one Hostinger domain. Keeps .env, storage, and uploads intact.
#
# Usage:
#   ./deploy/deploy-site.sh beyondtechworld.com
#
# Run from the site's public_html directory, or pass SITE_ROOT:
#   SITE_ROOT=~/domains/beyondtechworld.com/public_html ./deploy/deploy-site.sh

set -euo pipefail

DOMAIN="${1:-}"
SITE_ROOT="${SITE_ROOT:-}"

if [[ -z "$SITE_ROOT" ]]; then
    if [[ -z "$DOMAIN" ]]; then
        echo "Usage: $0 <domain>   OR   SITE_ROOT=/path/to/public_html $0"
        exit 1
    fi
    SITE_ROOT="$HOME/domains/$DOMAIN/public_html"
fi

if [[ ! -d "$SITE_ROOT" ]]; then
    echo "Site root not found: $SITE_ROOT"
    exit 1
fi

cd "$SITE_ROOT"

echo "Deploying in: $SITE_ROOT"

if [[ -f .env ]]; then
    cp .env ".env.backup.$(date +%Y%m%d_%H%M%S)"
fi

git pull origin main

# Ensure storefront/admin static assets exist after pull (upload dirs stay gitignored).
if [[ ! -f public/assets/css/style.css ]] || [[ ! -f public/vendor/tinymce/js/tinymce/tinymce.min.js ]]; then
    echo "Restoring tracked public static assets..."
    git checkout HEAD -- public/vendor public/assets/css public/assets/js public/assets/fonts public/css public/js public/favicon.ico public/robots.txt public/mix-manifest.json public/offline.html public/icons public/beep public/web.config 2>/dev/null || true
fi

if [[ -f composer.json ]] && command -v composer >/dev/null 2>&1; then
    composer install --no-dev --optimize-autoloader --no-interaction
fi

php artisan migrate --force
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan config:cache
php artisan view:cache

PUBLIC_HTACCESS="$SITE_ROOT/public/.htaccess"
CACHE_SNIPPET="$(dirname "$0")/static-cache.htaccess"
if [[ -f "$CACHE_SNIPPET" ]] && [[ -f "$PUBLIC_HTACCESS" ]]; then
    if ! grep -q "Static asset caching (PageSpeed quick win)" "$PUBLIC_HTACCESS" 2>/dev/null; then
        echo "" >> "$PUBLIC_HTACCESS"
        cat "$CACHE_SNIPPET" >> "$PUBLIC_HTACCESS"
        echo "Appended static cache rules to public/.htaccess"
    fi
fi

echo "Done. Database and .env were not replaced."
