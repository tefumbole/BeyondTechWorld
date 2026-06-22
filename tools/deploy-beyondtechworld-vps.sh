#!/usr/bin/env bash
# Deploy Beyond Company Ltd app to beyondtechworld.com — run on VPS from /var/www/beyondtechworld
# Usage: bash tools/deploy-beyondtechworld-vps.sh
set -euo pipefail

ROOT="${1:-/var/www/beyondtechworld}"
cd "$ROOT"

echo "==> 1. Ensure nginx owns port 80 (stop apache if needed)"
if systemctl is-active --quiet apache2 2>/dev/null; then
  systemctl stop apache2
  systemctl disable apache2
  echo "    Stopped apache2"
fi
systemctl start nginx || systemctl restart nginx

echo "==> 2. Pull latest code"
git pull || { echo "git pull failed — fix conflicts first"; exit 1; }

echo "==> 3. API env"
if [[ ! -f apps/api/.env ]]; then
  cp apps/api/.env.hostinger.example apps/api/.env
  echo "    Created apps/api/.env — EDIT DB_PASSWORD and JWT_SECRET, then re-run"
  exit 1
fi

echo "==> 4. Install & migrate"
cd apps/api && npm install && cd ../..
npm run db:migrate
if [[ "${IMPORT_EXPORT:-0}" == "1" ]] && [[ -d data/export ]] && ls data/export/*.json >/dev/null 2>&1; then
  echo "    IMPORT_EXPORT=1 — importing data/export (one-time / explicit only)"
  node apps/api/src/db/import-from-export.js "$ROOT/data/export" || true
else
  echo "    Skipping data/export import (set IMPORT_EXPORT=1 to enable)"
fi

echo "==> 5. Frontend env + build"
grep -q 'VITE_DATA_BACKEND=mysql' .env 2>/dev/null || echo 'VITE_DATA_BACKEND=mysql' >> .env
grep -q 'VITE_API_URL=' .env 2>/dev/null || echo 'VITE_API_URL=/api' >> .env
grep -q 'VITE_COMPANY_NAME=' .env 2>/dev/null || echo 'VITE_COMPANY_NAME=Beyond Company Ltd' >> .env
grep -q 'VITE_ADMIN_PHONE_NUMBER=' .env 2>/dev/null || echo 'VITE_ADMIN_PHONE_NUMBER=+237675321739' >> .env
grep -q 'VITE_SITE_URL=' .env 2>/dev/null || echo 'VITE_SITE_URL=https://beyondtechworld.com' >> .env
npm install
npm run build

echo "==> 6. Start API on port 3003 (PM2)"
npm install -g pm2 2>/dev/null || true
pm2 delete beyondtechworld-api 2>/dev/null || true
pm2 delete ecosystem.beyondtechworld 2>/dev/null || true
pm2 start src/main.js --name beyondtechworld-api --cwd "$ROOT/apps/api"
pm2 save

sleep 2
curl -sf http://127.0.0.1:3003/health || { echo "API health check failed"; exit 1; }
echo "    API OK"

echo "==> 7. SSL certificate (if missing)"
if [[ ! -f /etc/letsencrypt/live/beyondtechworld.com/fullchain.pem ]]; then
  echo "    Installing temporary HTTP-only nginx config for certbot..."
  install -m 644 "$ROOT/tools/nginx/beyondtechworld-http.conf" /etc/nginx/sites-available/beyondtechworld
  ln -sf /etc/nginx/sites-available/beyondtechworld /etc/nginx/sites-enabled/beyondtechworld
  nginx -t && systemctl reload nginx
  certbot certonly --webroot -w /var/www/letsencrypt \
    -d beyondtechworld.com -d www.beyondtechworld.com \
    --non-interactive --agree-tos -m admin@beyondtechworld.com || {
    echo "    certbot failed — ensure DNS points to this VPS and port 80 is open"
  }
fi

echo "==> 8. Nginx — apply all VPS vhost configs"
bash "$ROOT/tools/vps-apply-nginx.sh" "$ROOT"

echo ""
echo "Deploy complete. Test:"
echo "  curl -I https://beyondtechworld.com"
echo "  curl https://beyondtechworld.com/api/health"
echo "  Login: admin@beyondtechworld.com / ChangeMe@123456"
