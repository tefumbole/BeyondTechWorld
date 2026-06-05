#!/usr/bin/env bash
# Deploy AlphaBridge only — run on VPS from /var/www/alphabridge
# Usage: bash tools/deploy-alphabridge-vps.sh
set -euo pipefail

ROOT="${1:-/var/www/alphabridge}"
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
if [[ -d data/export ]] && ls data/export/*.json >/dev/null 2>&1; then
  node apps/api/src/db/import-from-export.js "$ROOT/data/export" || true
fi

echo "==> 5. Frontend env + build"
grep -q 'VITE_DATA_BACKEND=mysql' .env 2>/dev/null || {
  echo "VITE_DATA_BACKEND=mysql" >> .env
  echo "VITE_API_URL=/api" >> .env
}
npm install
npm run build

echo "==> 6. Start API on port 3003 (PM2)"
npm install -g pm2 2>/dev/null || true
pm2 delete alphabridge-api 2>/dev/null || true
pm2 start ecosystem.config.cjs
pm2 save

sleep 2
curl -sf http://127.0.0.1:3003/health || { echo "API health check failed"; exit 1; }
echo "    API OK"

echo "==> 7. Nginx — ensure /api proxy exists in alphabridge site"
SITE="/etc/nginx/sites-enabled/alphabridge"
if [[ -f "$SITE" ]] && ! grep -q 'location /api/' "$SITE"; then
  echo "    Add this block inside server { } in $SITE:"
  cat deploy/nginx-api.conf.snippet
  echo "    Then: nginx -t && systemctl reload nginx"
else
  nginx -t && systemctl reload nginx
fi

echo ""
echo "Deploy complete. Test:"
echo "  curl -I https://alpha-bridge.net"
echo "  curl https://alpha-bridge.net/api/health"
echo "  Login: admin@alpha-bridge.net / ChangeMe@123456"
