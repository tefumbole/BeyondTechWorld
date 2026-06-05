#!/usr/bin/env bash
# Run on VPS: bash tools/vps-check-ports.sh
set -euo pipefail

echo "========== PORT CONFLICT CHECK =========="
echo "Only nginx should listen on 80/443. Apps use 3001+ on 127.0.0.1"
echo ""
ss -tlnp | grep -E ':80 |:443 |:300[0-9]|:301[0-9]' || echo "(no matches)"

echo ""
echo "========== PM2 PROCESSES =========="
if command -v pm2 >/dev/null; then pm2 list; else echo "pm2 not installed"; fi

echo ""
echo "========== NGINX STATUS =========="
systemctl is-active nginx || true
nginx -t 2>&1 || true

echo ""
echo "========== NGINX SITES (server_name + proxy_pass) =========="
for f in /etc/nginx/sites-enabled/*; do
  echo "--- $(basename "$f") ---"
  grep -E 'server_name|proxy_pass|root ' "$f" 2>/dev/null | sed 's/^[ \t]*//' || true
done

echo ""
echo "========== DUPLICATE PORTS IN PROXY_PASS =========="
grep -rh 'proxy_pass' /etc/nginx/sites-enabled/ 2>/dev/null | sort | uniq -c | sort -rn

echo ""
echo "========== APACHE (should be inactive) =========="
systemctl is-active apache2 2>/dev/null || echo "apache2 inactive/absent"

echo ""
echo "========== ALPHABRIDGE HEALTH =========="
curl -s -o /dev/null -w "API :3003/health → HTTP %{http_code}\n" http://127.0.0.1:3003/health 2>/dev/null || echo "API :3003 not responding"
curl -s -o /dev/null -w "Site localhost → HTTP %{http_code}\n" -H "Host: alpha-bridge.net" http://127.0.0.1/ 2>/dev/null || true
test -f /var/www/alphabridge/dist/index.html && echo "dist/index.html exists" || echo "MISSING dist/index.html — run npm run build"

echo ""
echo "Done."
