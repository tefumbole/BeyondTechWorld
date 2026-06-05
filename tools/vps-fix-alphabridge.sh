#!/usr/bin/env bash
# Run ON THE VPS: bash /var/www/alphabridge/tools/vps-fix-alphabridge.sh
set -euo pipefail

ROOT=/var/www/alphabridge
cd "$ROOT"

echo "========== 1. DIAGNOSE =========="
ss -tlnp | grep -E ':80 |:443 |:300[0-9]' || true
systemctl is-active nginx || true
systemctl is-active apache2 2>/dev/null || echo "apache2: inactive"

echo ""
echo "========== 2. STOP APACHE (frees port 80) =========="
systemctl stop apache2 2>/dev/null || true
systemctl disable apache2 2>/dev/null || true
systemctl restart nginx

echo ""
echo "========== 3. UPDATE CODE =========="
git pull || { echo "git pull failed — run: rm -f apps/api/package-lock.json && git pull"; exit 1; }

echo ""
echo "========== 4. API ENV =========="
if [[ ! -f apps/api/.env ]]; then
  cp apps/api/.env.hostinger.example apps/api/.env
  echo "STOP: Edit apps/api/.env — set DB_PASSWORD and JWT_SECRET"
  echo "  nano apps/api/.env"
  exit 1
fi

echo ""
echo "========== 5. INSTALL + DB =========="
cd apps/api && npm install && cd ../..
npm run db:migrate
if [[ -d data/export ]] && [[ -f data/export/courses.json ]]; then
  node apps/api/src/db/import-from-export.js "$ROOT/data/export" || true
fi

echo ""
echo "========== 6. START API (port 3003) =========="
npm install -g pm2 2>/dev/null || true
pm2 delete alphabridge-api 2>/dev/null || true
pm2 start ecosystem.config.cjs
pm2 save
sleep 2
curl -sf http://127.0.0.1:3003/health && echo " API OK" || { echo "API failed — check apps/api/.env"; exit 1; }

echo ""
echo "========== 7. NGINX /api PROXY =========="
SITE=/etc/nginx/sites-enabled/alphabridge
if [[ -f "$SITE" ]] && grep -q 'location /api/' "$SITE"; then
  echo "/api proxy already configured"
else
  echo "Add /api/ block manually — see deploy/nginx-api.conf.snippet"
  echo "  nano $SITE"
  echo "Then: nginx -t && systemctl reload nginx"
fi
nginx -t && systemctl reload nginx

echo ""
echo "========== 8. BUILD FRONTEND =========="
grep -q '^VITE_DATA_BACKEND=mysql' .env 2>/dev/null || echo 'VITE_DATA_BACKEND=mysql' >> .env
grep -q '^VITE_API_URL=' .env 2>/dev/null || echo 'VITE_API_URL=/api' >> .env
npm install
npm run build

echo ""
echo "========== 9. VERIFY =========="
curl -sI -H "Host: alpha-bridge.net" http://127.0.0.1/ | head -3
curl -sf http://127.0.0.1:3003/health | head -c 120; echo
echo ""
echo "Done. Open https://alpha-bridge.net"
echo "Login: admin@alpha-bridge.net / ChangeMe@123456"
